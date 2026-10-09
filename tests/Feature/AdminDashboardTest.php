<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Space;
use App\Models\SpaceInventory;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private Space $space;

    private User $staff;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'timezone' => 'Asia/Jakarta']);
        $this->space = $this->venue->spaces()->create(['name' => 'Studio', 'slug' => 'studio', 'base_price' => 500000, 'layouts' => ['banquet' => 100], 'is_active' => true]);
        $this->staff = User::factory()->create();
        $this->venue->users()->attach($this->staff->id, ['role' => 'staff', 'status' => 'active']);
        $this->today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
    }

    private function booking(string $status, int $startOffset, ?\DateTimeInterface $createdAt = null, ?Venue $venue = null, ?Space $space = null): Booking
    {
        $booking = Booking::create([
            'reference' => 'EV-'.Str::upper(Str::random(6)), 'venue_id' => ($venue ?? $this->venue)->id, 'space_id' => ($space ?? $this->space)->id,
            'guest_name' => 'Guest '.Str::random(4), 'event_type' => 'corporate', 'event_start' => $this->today->addDays($startOffset), 'event_end' => $this->today->addDays($startOffset + 1),
            'guests' => 80, 'total_price' => 1000000, 'status' => $status,
        ]);

        if ($createdAt) {
            $booking->forceFill(['created_at' => $createdAt])->save();
        }

        return $booking;
    }

    private function dashboard()
    {
        return $this->actingAs($this->staff)->get(route('admin.dashboard'));
    }

    public function test_pending_requests_older_than_a_day_are_flagged_as_waiting(): void
    {
        $this->freezeTime();
        $this->booking(Booking::STATUS_PENDING, 5);
        $this->booking(Booking::STATUS_PENDING, 6, now()->subHours(25));
        $this->booking(Booking::STATUS_CONFIRMED, 7, now()->subDays(3));

        $this->dashboard()->assertOk()->assertSee('1 waiting over 24h')->assertSee('border-amber-300', false);
    }

    public function test_nothing_is_flagged_when_every_pending_request_is_recent(): void
    {
        $this->booking(Booking::STATUS_PENDING, 5);

        $this->dashboard()->assertOk()->assertSee('None waiting over 24h')->assertDontSee('border-amber-300', false);
    }

    public function test_upcoming_events_count_and_list_only_confirmed_bookings_in_the_next_fourteen_days(): void
    {
        $soon = $this->booking(Booking::STATUS_CONFIRMED, 0);
        $this->booking(Booking::STATUS_CONFIRMED, 13);
        $this->booking(Booking::STATUS_CONFIRMED, 14);
        $this->booking(Booking::STATUS_CONFIRMED, -1);
        $this->booking(Booking::STATUS_PENDING, 2);
        $this->booking(Booking::STATUS_CANCELLED, 2);

        $response = $this->dashboard()->assertOk()->assertSee('Events · next 14 days')->assertSee($soon->guest_name);

        $this->assertCount(2, $response->viewData('upcomingEvents'));
        $this->assertSame(2, $response->viewData('stats')['upcoming_events']);
    }

    public function test_booked_days_are_booked_over_total_rooms_for_the_next_thirty_days_of_active_spaces(): void
    {
        $hidden = $this->venue->spaces()->create(['name' => 'Hidden', 'slug' => 'hidden', 'base_price' => 1, 'layouts' => ['banquet' => 10], 'is_active' => false]);
        foreach ([[$this->space, 0, 10, 4], [$this->space, 29, 10, 6], [$this->space, 30, 10, 10], [$hidden, 1, 10, 10]] as [$space, $offset, $total, $booked]) {
            SpaceInventory::create(['space_id' => $space->id, 'event_date' => $this->today->addDays($offset)->toDateString(), 'total_units' => $total, 'booked_units' => $booked, 'price' => 1]);
        }

        $response = $this->dashboard()->assertOk()->assertSee('Booked days · next 30')->assertSee('50%');

        $this->assertSame(50, $response->viewData('stats')['occupancy_percent']);
    }

    public function test_booked_days_are_a_dash_until_dates_are_open(): void
    {
        $this->dashboard()->assertOk()->assertSee('No dates open yet');

        $this->assertNull($this->dashboard()->viewData('stats')['occupancy_percent']);
    }

    public function test_figures_never_include_another_venues_data(): void
    {
        $this->freezeTime();
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $foreign = $other->spaces()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'layouts' => ['banquet' => 10], 'is_active' => true]);
        $this->booking(Booking::STATUS_CONFIRMED, 1, null, $other, $foreign);
        $this->booking(Booking::STATUS_PENDING, 1, now()->subDays(2), $other, $foreign);
        SpaceInventory::create(['space_id' => $foreign->id, 'event_date' => $this->today->toDateString(), 'total_units' => 5, 'booked_units' => 5, 'price' => 1]);
        $conversation = Conversation::create(['venue_id' => $other->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);
        HandoverRequest::create(['conversation_id' => $conversation->id, 'reason' => 'complaint', 'summary' => 'Foreign complaint']);

        $stats = $this->dashboard()->assertOk()->viewData('stats');

        $this->assertSame(0, $stats['upcoming_events']);
        $this->assertSame(0, $stats['pending_bookings']);
        $this->assertSame(0, $stats['waiting_bookings']);
        $this->assertSame(0, $stats['open_handovers']);
        $this->assertNull($stats['occupancy_percent']);
    }

    public function test_every_admin_page_has_a_menu_for_phones_and_marks_the_current_page(): void
    {
        $this->dashboard()
            ->assertOk()
            ->assertSee('<details class="group">', false)
            ->assertSee('aria-current="page"', false);

        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertOk()->assertSee('<details class="group">', false);
    }
}
