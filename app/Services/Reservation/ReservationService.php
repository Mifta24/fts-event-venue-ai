<?php

namespace App\Services\Reservation;

use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\UnitInventory;
use App\Models\UnitType;
use App\Notifications\BookingRequestReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * The single place where stay prices, availability and reservation requests
 * are computed. Both the AI concierge tools and the guest-facing reservation
 * wizard go through here, so they can never disagree.
 */
class ReservationService
{
    /** Apartments take long stays, so a request may cover up to about three months. */
    public const MAX_NIGHTS = 90;

    /**
     * Whether the requested party fits the unit type across the requested units.
     */
    public function fitsOccupancy(UnitType $unitType, int $adults, int $children, int $units): bool
    {
        return $adults <= $unitType->max_adults * $units
            && ($adults + $children) <= $unitType->maxOccupancy() * $units;
    }

    /**
     * Prices a stay across every night in range, then takes off the
     * apartment's weekly or monthly long-stay discount. Returns null if any
     * night lacks enough free units — this is the one place price and
     * availability truth comes from, never the model.
     *
     * @return array{nights: int, nightly: list<array{date: string, price: float}>, unit_total: float, extra_bed_total: float, subtotal: float, discount_percent: int, discount_total: float, grand_total: float, min_available_units: int}|null
     */
    public function quote(
        UnitType $unitType,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $units = 1,
        bool $extraBed = false,
        bool $lockForUpdate = false,
    ): ?array {
        $nights = $checkIn->diffInDays($checkOut);

        if ($nights < 1) {
            return null;
        }

        $query = UnitInventory::where('unit_type_id', $unitType->id)
            ->whereDate('stay_date', '>=', $checkIn->toDateString())
            ->whereDate('stay_date', '<=', $checkOut->subDay()->toDateString())
            ->orderBy('stay_date');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $inventory = $query->get();

        if ($inventory->count() < $nights) {
            return null;
        }

        $nightly = [];
        $perUnitTotal = 0.0;
        $minAvailable = PHP_INT_MAX;

        foreach ($inventory as $night) {
            if ($night->availableUnits() < $units) {
                return null;
            }

            $nightly[] = ['date' => $night->stay_date->toDateString(), 'price' => (float) $night->price];
            $perUnitTotal += (float) $night->price;
            $minAvailable = min($minAvailable, $night->availableUnits());
        }

        $unitTotal = $perUnitTotal * $units;
        $extraBedTotal = $extraBed && $unitType->extra_bed_available
            ? (float) $unitType->extra_bed_price * $nights * $units
            : 0.0;

        $subtotal = $unitTotal + $extraBedTotal;
        $discountPercent = $unitType->apartment->longStayDiscountPercent($nights);
        $discountTotal = round($subtotal * $discountPercent / 100, 2);

        return [
            'nights' => $nights,
            'nightly' => $nightly,
            'unit_total' => $unitTotal,
            'extra_bed_total' => $extraBedTotal,
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_total' => $discountTotal,
            'grand_total' => $subtotal - $discountTotal,
            'min_available_units' => $minAvailable,
        ];
    }

    /**
     * Creates a pending reservation request and holds the inventory for it.
     * Returns null when the unit is no longer available for those nights.
     *
     * @param  array{check_in: CarbonImmutable, check_out: CarbonImmutable, adults: int, children?: int, units?: int, extra_bed?: bool, guest_name: string, guest_email?: ?string, guest_phone?: ?string, contact_type?: ?string, locale?: ?string, notes?: ?string}  $data
     */
    public function createRequest(Apartment $apartment, UnitType $unitType, array $data, ?Conversation $conversation = null): ?Booking
    {
        $units = $data['units'] ?? 1;
        $extraBed = (bool) ($data['extra_bed'] ?? false);

        $booking = DB::transaction(function () use ($apartment, $unitType, $data, $conversation, $units, $extraBed) {
            $quote = $this->quote($unitType, $data['check_in'], $data['check_out'], $units, $extraBed, lockForUpdate: true);

            if (! $quote) {
                return null;
            }

            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'apartment_id' => $apartment->id,
                'unit_type_id' => $unitType->id,
                'conversation_id' => $conversation?->id,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'contact_type' => $data['contact_type'] ?? null,
                'locale' => $data['locale'] ?? null,
                'check_in' => $data['check_in']->toDateString(),
                'check_out' => $data['check_out']->toDateString(),
                'adults' => $data['adults'],
                'children' => $data['children'] ?? 0,
                'unit_count' => $units,
                'extra_bed' => $extraBed && $unitType->extra_bed_available,
                'total_price' => $quote['grand_total'],
                'status' => Booking::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->adjustInventory($booking, +1);

            return $booking;
        });

        if ($booking) {
            $apartment->notifyStaff(new NewBookingRequest($booking));
            $this->notifyGuest($booking, new BookingRequestReceived($booking));
        }

        return $booking;
    }

    /**
     * Confirms or cancels a booking. Returns false when its current status
     * does not allow the change, so a cancelled booking can never come back
     * without its units being held again. Cancelling gives the units back.
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
     * Gives the units held by a booking back to the inventory.
     */
    public function releaseInventory(Booking $booking): void
    {
        $this->adjustInventory($booking, -1);
    }

    private function adjustInventory(Booking $booking, int $direction): void
    {
        UnitInventory::where('unit_type_id', $booking->unit_type_id)
            ->whereDate('stay_date', '>=', $booking->check_in->toDateString())
            ->whereDate('stay_date', '<=', $booking->check_out->copy()->subDay()->toDateString())
            ->{$direction > 0 ? 'increment' : 'decrement'}('booked_units', $booking->unit_count);
    }
}
