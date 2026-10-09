<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Space;
use App\Models\SpaceInventory;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationRequestTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private Space $ballroom;

    private Space $pavilion;

    private CarbonImmutable $today;

    private string $eventStart;

    private string $eventEnd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'timezone' => 'Asia/Makassar',
            'default_locale' => 'en', 'whatsapp' => '+62 812-3456-7890', 'phone' => '+62 361 771234', 'email' => 'events@demo.test',
        ]);

        $this->ballroom = $this->venue->spaces()->create([
            'name' => 'Grand Ballroom', 'slug' => 'grand-ballroom', 'base_price' => 10000000, 'layouts' => ['banquet' => 200, 'theatre' => 300],
            'catering_available' => true, 'catering_price' => 100000, 'is_active' => true, 'sort_order' => 0,
        ]);
        $this->pavilion = $this->venue->spaces()->create([
            'name' => 'Pavilion', 'slug' => 'pavilion', 'base_price' => 5000000, 'layouts' => ['banquet' => 80],
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        $this->eventStart = $this->today->addDays(10)->toDateString();
        $this->eventEnd = $this->today->addDays(11)->toDateString();

        foreach ([$this->ballroom->id => [2, 10000000], $this->pavilion->id => [1, 5000000]] as $spaceId => [$rooms, $price]) {
            foreach (range(10, 12) as $offset) {
                SpaceInventory::create([
                    'space_id' => $spaceId, 'event_date' => $this->today->addDays($offset)->toDateString(),
                    'total_units' => $rooms, 'booked_units' => 0, 'price' => $price,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function event(array $overrides = []): array
    {
        return [
            'space_slug' => 'grand-ballroom', 'event_start' => $this->eventStart, 'event_end' => $this->eventEnd,
            'event_type' => 'wedding', 'guests' => 150, 'setup_style' => 'banquet', 'locale' => 'en',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function request(array $overrides = []): array
    {
        return $this->event([
            'guest_name' => 'John Tan', 'contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 1111',
            'special_request' => 'Rehearsal the day before', ...$overrides,
        ]);
    }

    public function test_quote_prices_every_event_day_and_adds_catering_per_guest_per_day(): void
    {
        $this->postJson('/demo/reservation/quote', $this->event(['catering' => true]))
            ->assertOk()
            ->assertJson([
                'available' => true, 'days' => 2, 'space_total' => 20000000, 'catering_total' => 30000000,
                'grand_total' => 50000000, 'currency' => 'IDR',
            ]);
    }

    public function test_a_one_day_event_uses_the_same_date_for_both_ends(): void
    {
        $this->postJson('/demo/reservation/quote', $this->event(['event_end' => $this->eventStart]))
            ->assertOk()
            ->assertJson(['days' => 1, 'grand_total' => 10000000]);
    }

    public function test_catering_is_ignored_for_a_space_that_does_not_offer_it(): void
    {
        $this->postJson('/demo/reservation/quote', $this->event(['space_slug' => 'pavilion', 'guests' => 60, 'catering' => true]))
            ->assertOk()
            ->assertJson(['catering_total' => 0, 'grand_total' => 10000000]);
    }

    public function test_quote_rejects_invalid_dates_in_the_guest_language(): void
    {
        $yesterday = $this->today->subDay()->toDateString();

        $this->postJson('/demo/reservation/quote', $this->event(['event_start' => $yesterday, 'locale' => 'id']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.event_start.0', 'Tanggal acara tidak boleh di masa lalu.');

        $this->postJson('/demo/reservation/quote', $this->event(['event_end' => $this->today->addDays(9)->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.event_end.0', 'The last event day cannot be before the first.');
    }

    public function test_quote_rejects_events_longer_than_the_limit(): void
    {
        $tooLong = CarbonImmutable::parse($this->eventStart)->addDays(ReservationService::MAX_DAYS)->toDateString();

        $this->postJson('/demo/reservation/quote', $this->event(['event_end' => $tooLong]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_end');
    }

    public function test_quote_rejects_a_headcount_the_chosen_setup_cannot_seat(): void
    {
        $this->postJson('/demo/reservation/quote', $this->event(['guests' => 250]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.guests.0', 'This space does not seat that many guests in the chosen setup. Choose a larger space or another layout.');

        $this->postJson('/demo/reservation/quote', $this->event(['guests' => 250, 'setup_style' => 'theatre']))->assertOk();
        $this->postJson('/demo/reservation/quote', $this->event(['guests' => 50, 'space_slug' => 'pavilion', 'setup_style' => 'theatre']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guests');
    }

    public function test_quote_rejects_unknown_and_inactive_spaces(): void
    {
        $this->ballroom->update(['is_active' => false]);

        $this->postJson('/demo/reservation/quote', $this->event())->assertUnprocessable()->assertJsonValidationErrors('space_slug');
        $this->postJson('/demo/reservation/quote', $this->event(['space_slug' => 'nope']))->assertUnprocessable()->assertJsonValidationErrors('space_slug');
    }

    public function test_quote_rejects_an_unknown_event_type(): void
    {
        $this->postJson('/demo/reservation/quote', $this->event(['event_type' => 'rave']))->assertUnprocessable()->assertJsonValidationErrors('event_type');
    }

    public function test_unavailable_space_offers_alternatives_that_fit_the_guest_list(): void
    {
        SpaceInventory::where('space_id', $this->ballroom->id)->update(['booked_units' => 2]);

        $this->postJson('/demo/reservation/quote', $this->event(['guests' => 60]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.space_slug.0', 'This space is not available on those dates.')
            ->assertJsonPath('alternatives.0.slug', 'pavilion')
            ->assertJsonPath('alternatives.0.total', 10000000);
    }

    public function test_submitting_creates_a_pending_request_holds_the_days_and_returns_handover_links(): void
    {
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $response = $this->postJson('/demo/reservation', $this->request(['catering' => true, 'guest_token' => $conversation->guest_token]))
            ->assertCreated()
            ->assertJsonPath('status', Booking::STATUS_PENDING)
            ->assertJsonPath('total', 50000000)
            ->assertJsonPath('handover.phone_url', 'tel:+62361771234');

        $booking = Booking::firstOrFail();
        $this->assertMatchesRegularExpression('/^EV-[A-Z0-9]{6}$/', $booking->reference);
        $response->assertJsonPath('reference', $booking->reference);
        $this->assertSame('wedding', $booking->event_type);
        $this->assertSame(150, $booking->guests);
        $this->assertSame('banquet', $booking->setup_style);
        $this->assertTrue($booking->catering);
        $this->assertSame(2, $booking->days());
        $this->assertSame('whatsapp', $booking->contact_type);
        $this->assertSame('+62 812 0000 1111', $booking->guest_phone);
        $this->assertNull($booking->guest_email);
        $this->assertSame('Rehearsal the day before', $booking->notes);
        $this->assertSame($conversation->id, $booking->conversation_id);
        $this->assertSame([1, 1, 0], SpaceInventory::where('space_id', $this->ballroom->id)->orderBy('event_date')->pluck('booked_units')->all());

        $whatsapp = $response->json('handover.whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $whatsapp);
        $message = urldecode(substr($whatsapp, strlen('https://wa.me/6281234567890?text=')));
        $this->assertStringContainsString('Name: John Tan', $message);
        $this->assertStringContainsString('Event: Wedding', $message);
        $this->assertStringContainsString('Date: ', $message);
        $this->assertStringContainsString('Guests: 150', $message);
        $this->assertStringContainsString('Setup: Banquet', $message);
        $this->assertStringContainsString('Space: Grand Ballroom', $message);
        $this->assertStringContainsString('Special request: Rehearsal the day before', $message);
        $this->assertStringContainsString("Reference: {$booking->reference}", $message);
        $this->assertStringStartsWith('mailto:events@demo.test?subject=', $response->json('handover.email_url'));
    }

    public function test_email_contacts_are_stored_as_email_and_validated(): void
    {
        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.contact_value.0', 'Please enter a valid email address.');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'phone', 'contact_value' => 'call me']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_value');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'john@example.com']))->assertCreated();

        $booking = Booking::firstOrFail();
        $this->assertSame('john@example.com', $booking->guest_email);
        $this->assertNull($booking->guest_phone);
    }

    public function test_the_last_free_room_can_only_be_requested_once(): void
    {
        $this->postJson('/demo/reservation', $this->request(['space_slug' => 'pavilion', 'guests' => 60]))->assertCreated();

        $this->postJson('/demo/reservation', $this->request(['space_slug' => 'pavilion', 'guests' => 60, 'guest_name' => 'Late Guest']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.space_slug.0', 'This space is not available on those dates.');

        $this->assertSame(1, Booking::count());
    }

    public function test_reference_is_unique_per_request(): void
    {
        $this->postJson('/demo/reservation', $this->request())->assertCreated();
        $this->postJson('/demo/reservation', $this->request())->assertCreated();

        $this->assertSame(2, Booking::distinct()->count('reference'));
    }

    public function test_unpublished_venues_reject_reservations(): void
    {
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->postJson('/draft/reservation', $this->request())->assertNotFound();
        $this->postJson('/draft/reservation/quote', $this->event())->assertNotFound();
    }

    public function test_reservation_requests_are_rate_limited(): void
    {
        foreach (range(1, 20) as $attempt) {
            $this->postJson('/demo/reservation/quote', $this->event())->assertOk();
        }

        $this->postJson('/demo/reservation/quote', $this->event())->assertTooManyRequests();
    }

    public function test_cancelling_a_multi_day_booking_releases_every_held_day(): void
    {
        $this->postJson('/demo/reservation', $this->request())->assertCreated();
        $booking = Booking::firstOrFail();

        $user = User::factory()->create();
        $this->venue->users()->attach($user->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($user)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])
            ->assertRedirect();

        $this->assertSame([0, 0, 0], SpaceInventory::where('space_id', $this->ballroom->id)->orderBy('event_date')->pluck('booked_units')->all());
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }

    public function test_weekday_and_multi_day_events_are_quoted_with_the_better_discount(): void
    {
        $this->venue->update(['weekday_discount_percent' => 15, 'multiday_discount_percent' => 10]);

        $monday = $this->today->next('monday')->addWeeks(2);
        foreach (range(0, 6) as $offset) {
            SpaceInventory::create([
                'space_id' => $this->ballroom->id, 'event_date' => $monday->addDays($offset)->toDateString(),
                'total_units' => 1, 'booked_units' => 0, 'price' => 10000000,
            ]);
        }

        $event = fn (int $startOffset, int $days) => $this->event([
            'event_start' => $monday->addDays($startOffset)->toDateString(),
            'event_end' => $monday->addDays($startOffset + $days - 1)->toDateString(),
        ]);

        // Saturday only: neither a weekday nor a multi-day event.
        $this->postJson('/demo/reservation/quote', $event(5, 1))
            ->assertOk()
            ->assertJsonPath('discount_percent', 0)
            ->assertJsonPath('grand_total', 10000000);

        // Monday and Tuesday: a weekday event.
        $this->postJson('/demo/reservation/quote', $event(0, 2))
            ->assertOk()
            ->assertJsonPath('subtotal', 20000000)
            ->assertJsonPath('discount_percent', 15)
            ->assertJsonPath('discount_total', 3000000)
            ->assertJsonPath('grand_total', 17000000);

        // Friday to Sunday: three days, so the multi-day rate, but not a weekday event.
        $this->postJson('/demo/reservation/quote', $event(4, 3))
            ->assertOk()
            ->assertJsonPath('discount_percent', 10)
            ->assertJsonPath('grand_total', 27000000);

        // Monday to Wednesday: both apply and the weekday rate wins.
        $this->postJson('/demo/reservation/quote', $event(0, 3))
            ->assertOk()
            ->assertJsonPath('discount_percent', 15)
            ->assertJsonPath('grand_total', 25500000);

        $this->postJson('/demo/reservation', [...$event(0, 3), 'guest_name' => 'Weekday Booker', 'contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 2222'])
            ->assertCreated()
            ->assertJsonPath('total', 25500000);
    }

    public function test_events_are_not_discounted_when_the_venue_offers_no_event_rates(): void
    {
        $this->postJson('/demo/reservation/quote', $this->event(['event_end' => $this->today->addDays(12)->toDateString()]))
            ->assertOk()
            ->assertJsonPath('discount_percent', 0)
            ->assertJsonPath('grand_total', 30000000);
    }
}
