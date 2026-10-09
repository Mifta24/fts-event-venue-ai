<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Space;
use App\Models\SpaceInventory;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\BookingStatusChanged;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingStatusTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private Space $space;

    private User $staff;

    private CarbonImmutable $eventStart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->space = $this->venue->spaces()->create(['name' => 'Studio Hall', 'slug' => 'studio', 'base_price' => 500000, 'layouts' => ['banquet' => 100], 'is_active' => true]);

        $this->staff = User::factory()->create();
        $this->venue->users()->attach($this->staff->id, ['role' => 'owner', 'status' => 'active']);

        $this->eventStart = CarbonImmutable::now()->addDays(5)->startOfDay();
        foreach ([0, 1] as $offset) {
            SpaceInventory::create(['space_id' => $this->space->id, 'event_date' => $this->eventStart->addDays($offset)->toDateString(), 'total_units' => 2, 'booked_units' => 0, 'price' => 500000]);
        }
    }

    private function holdDates(): Booking
    {
        return app(ReservationService::class)->createRequest($this->venue, $this->space, [
            'event_start' => $this->eventStart, 'event_end' => $this->eventStart->addDay(), 'event_type' => 'wedding', 'guests' => 80,
            'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222', 'contact_type' => 'whatsapp',
        ]);
    }

    /**
     * @return list<int>
     */
    private function bookedRooms(): array
    {
        return SpaceInventory::orderBy('event_date')->pluck('booked_units')->all();
    }

    private function setStatus(Booking $booking, string $status)
    {
        return $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => $status]);
    }

    public function test_confirming_a_pending_booking_keeps_its_dates_held(): void
    {
        $booking = $this->holdDates();

        $this->setStatus($booking, Booking::STATUS_CONFIRMED)->assertRedirect()->assertSessionHas('status');

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
        $this->assertSame([1, 1], $this->bookedRooms());
    }

    public function test_cancelling_a_confirmed_booking_gives_its_dates_back(): void
    {
        $booking = $this->holdDates();
        $this->setStatus($booking, Booking::STATUS_CONFIRMED);

        $this->setStatus($booking, Booking::STATUS_CANCELLED)->assertRedirect()->assertSessionHas('status');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame([0, 0], $this->bookedRooms());
    }

    public function test_a_cancelled_booking_cannot_be_confirmed_again(): void
    {
        $booking = $this->holdDates();
        $this->setStatus($booking, Booking::STATUS_CANCELLED);

        $this->setStatus($booking, Booking::STATUS_CONFIRMED)->assertRedirect()->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame([0, 0], $this->bookedRooms());
    }

    public function test_cancelling_twice_releases_the_dates_only_once(): void
    {
        $first = $this->holdDates();
        $this->holdDates();

        $this->setStatus($first, Booking::STATUS_CANCELLED);
        $this->setStatus($first, Booking::STATUS_CANCELLED)->assertSessionHas('error');

        $this->assertSame([1, 1], $this->bookedRooms());
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $booking = $this->holdDates();

        $this->setStatus($booking, Booking::STATUS_PENDING)->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_the_booking_list_offers_only_the_moves_a_booking_can_make(): void
    {
        $booking = $this->holdDates();
        $this->setStatus($booking, Booking::STATUS_CONFIRMED);

        $this->actingAs($this->staff)->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertDontSee('>Confirm</button>', false)
            ->assertSee('>Cancel</button>', false);
    }

    public function test_a_space_with_open_bookings_cannot_be_deleted(): void
    {
        $this->holdDates();

        $this->actingAs($this->staff)->delete(route('admin.spaces.destroy', $this->space))->assertSessionHas('error');

        $this->assertNotSoftDeleted($this->space);
    }

    public function test_a_space_whose_bookings_are_cancelled_can_be_deleted_and_the_booking_list_still_works(): void
    {
        $booking = $this->holdDates();
        $this->setStatus($booking, Booking::STATUS_CANCELLED);

        $this->actingAs($this->staff)->delete(route('admin.spaces.destroy', $this->space))->assertSessionHas('status');

        $this->assertSoftDeleted($this->space);
        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertOk()->assertSee($booking->reference)->assertSee('Studio Hall');
    }

    public function test_a_space_whose_open_bookings_ended_in_the_past_can_be_deleted(): void
    {
        $booking = $this->holdDates();
        $booking->update(['event_start' => now()->subDays(10), 'event_end' => now()->subDays(9)]);

        $this->actingAs($this->staff)->delete(route('admin.spaces.destroy', $this->space))->assertSessionHas('status');

        $this->assertSoftDeleted($this->space);
    }

    public function test_guests_with_an_email_are_told_when_their_booking_is_confirmed_or_cancelled(): void
    {
        Notification::fake();
        $booking = $this->holdDates();
        $booking->update(['guest_email' => 'ayu@example.test']);

        $this->setStatus($booking, Booking::STATUS_CONFIRMED);
        $this->setStatus($booking, Booking::STATUS_CANCELLED);
        $this->setStatus($booking, Booking::STATUS_CONFIRMED);

        Notification::assertSentOnDemandTimes(BookingStatusChanged::class, 2);
    }
}
