<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Space;
use App\Models\SpaceInventory;
use App\Models\Venue;
use App\Notifications\BookingRequestReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * The single place where event prices, availability and booking requests
 * are computed. Both the AI planner tools and the guest-facing reservation
 * wizard go through here, so they can never disagree.
 */
class ReservationService
{
    /** An event may run for up to two weeks online; longer ones go to the events team. */
    public const MAX_DAYS = 14;

    /**
     * Whether the space can host this many guests, in the given setup or, when
     * no setup is chosen, in its roomiest one.
     */
    public function fitsCapacity(Space $space, int $guests, ?string $layout = null): bool
    {
        $capacity = $layout ? $space->capacityFor($layout) : $space->maxGuests();

        return $capacity !== null && $guests >= 1 && $guests <= $capacity;
    }

    /**
     * Prices an event across every day in range (both ends included), adds
     * catering per guest and per day when asked, then takes off the venue's
     * weekday or multi-day discount. Returns null if any day is already taken
     * — this is the one place price and availability truth comes from, never
     * the model.
     *
     * @return array{days: int, daily: list<array{date: string, price: float}>, space_total: float, catering_total: float, subtotal: float, discount_percent: int, discount_total: float, grand_total: float, min_available_units: int}|null
     */
    public function quote(
        Space $space,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $guests = 0,
        bool $catering = false,
        bool $lockForUpdate = false,
    ): ?array {
        $days = (int) $start->diffInDays($end) + 1;

        if ($days < 1) {
            return null;
        }

        $query = SpaceInventory::where('space_id', $space->id)
            ->whereDate('event_date', '>=', $start->toDateString())
            ->whereDate('event_date', '<=', $end->toDateString())
            ->orderBy('event_date');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $inventory = $query->get();

        if ($inventory->count() < $days) {
            return null;
        }

        $daily = [];
        $spaceTotal = 0.0;
        $minAvailable = PHP_INT_MAX;

        foreach ($inventory as $day) {
            if ($day->availableUnits() < 1) {
                return null;
            }

            $daily[] = ['date' => $day->event_date->toDateString(), 'price' => (float) $day->price];
            $spaceTotal += (float) $day->price;
            $minAvailable = min($minAvailable, $day->availableUnits());
        }

        $cateringTotal = $catering && $space->catering_available
            ? (float) $space->catering_price * max(0, $guests) * $days
            : 0.0;

        $subtotal = $spaceTotal + $cateringTotal;
        $discountPercent = $space->venue->eventDiscountPercent($start, $days);
        $discountTotal = round($subtotal * $discountPercent / 100, 2);

        return [
            'days' => $days,
            'daily' => $daily,
            'space_total' => $spaceTotal,
            'catering_total' => $cateringTotal,
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_total' => $discountTotal,
            'grand_total' => $subtotal - $discountTotal,
            'min_available_units' => $minAvailable,
        ];
    }

    /**
     * Creates a pending event request and holds the dates for it. Returns
     * null when the space is no longer free on those days.
     *
     * @param  array{event_start: CarbonImmutable, event_end: CarbonImmutable, event_type: string, guests: int, setup_style?: ?string, catering?: bool, guest_name: string, guest_email?: ?string, guest_phone?: ?string, contact_type?: ?string, locale?: ?string, notes?: ?string}  $data
     */
    public function createRequest(Venue $venue, Space $space, array $data, ?Conversation $conversation = null): ?Booking
    {
        $catering = (bool) ($data['catering'] ?? false) && $space->catering_available;

        $booking = DB::transaction(function () use ($venue, $space, $data, $conversation, $catering) {
            $quote = $this->quote($space, $data['event_start'], $data['event_end'], $data['guests'], $catering, lockForUpdate: true);

            if (! $quote) {
                return null;
            }

            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'venue_id' => $venue->id,
                'space_id' => $space->id,
                'conversation_id' => $conversation?->id,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'contact_type' => $data['contact_type'] ?? null,
                'locale' => $data['locale'] ?? null,
                'event_type' => $data['event_type'],
                'event_start' => $data['event_start']->toDateString(),
                'event_end' => $data['event_end']->toDateString(),
                'guests' => $data['guests'],
                'setup_style' => $data['setup_style'] ?? null,
                'catering' => $catering,
                'total_price' => $quote['grand_total'],
                'status' => Booking::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->adjustInventory($booking, +1);

            return $booking;
        });

        if ($booking) {
            $venue->notifyStaff(new NewBookingRequest($booking));
            $this->notifyGuest($booking, new BookingRequestReceived($booking));
        }

        return $booking;
    }

    /**
     * Confirms or cancels a booking. Returns false when its current status
     * does not allow the change, so a cancelled booking can never come back
     * without its dates being held again. Cancelling frees the dates.
     */
    public function changeStatus(Booking $booking, string $status): bool
    {
        $changed = DB::transaction(function () use ($booking, $status) {
            $current = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (! $current->canTransitionTo($status)) {
                return false;
            }

            if ($status === Booking::STATUS_CANCELLED) {
                $this->releaseInventory($current);
            }

            $current->update(['status' => $status]);

            return true;
        });

        $booking->refresh();

        if ($changed) {
            $this->notifyGuest($booking, new BookingStatusChanged($booking));
        }

        return $changed;
    }

    /**
     * Guests who left an email address get a message; those who chose
     * WhatsApp or phone are answered by the team instead.
     */
    private function notifyGuest(Booking $booking, Notification $notification): void
    {
        if (filled($booking->guest_email)) {
            NotificationFacade::route('mail', $booking->guest_email)->notify($notification->locale($booking->locale));
        }
    }

    /**
     * Gives the dates held by a booking back to the inventory.
     */
    public function releaseInventory(Booking $booking): void
    {
        $this->adjustInventory($booking, -1);
    }

    private function adjustInventory(Booking $booking, int $direction): void
    {
        SpaceInventory::where('space_id', $booking->space_id)
            ->whereDate('event_date', '>=', $booking->event_start->toDateString())
            ->whereDate('event_date', '<=', $booking->event_end->toDateString())
            ->{$direction > 0 ? 'increment' : 'decrement'}('booked_units');
    }
}
