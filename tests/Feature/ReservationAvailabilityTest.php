<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\SpaceInventory;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'timezone' => 'Asia/Makassar']);
        $this->today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
    }

    private function space(string $slug, bool $active = true): Space
    {
        return $this->venue->spaces()->create(['name' => $slug, 'slug' => $slug, 'base_price' => 500000, 'layouts' => ['banquet' => 100], 'is_active' => $active]);
    }

    private function day(Space $space, int $offset, int $total = 2, int $booked = 0): void
    {
        SpaceInventory::create(['space_id' => $space->id, 'event_date' => $this->today->addDays($offset)->toDateString(), 'total_units' => $total, 'booked_units' => $booked, 'price' => 500000]);
    }

    /**
     * @return list<string>
     */
    private function openDays(): array
    {
        return $this->getJson('/demo/reservation/availability')->assertOk()->json('dates');
    }

    public function test_a_day_is_open_while_any_active_space_has_a_free_room(): void
    {
        $studio = $this->space('studio');
        $suite = $this->space('suite');
        $this->day($studio, 1, total: 1, booked: 1);
        $this->day($suite, 1, total: 2, booked: 1);

        $this->assertSame([$this->today->addDay()->toDateString()], $this->openDays());
    }

    public function test_a_day_is_closed_when_every_space_is_booked_or_nothing_is_open(): void
    {
        $studio = $this->space('studio');
        $this->day($studio, 1, total: 2, booked: 2);
        $this->day($studio, 3, total: 0);

        $this->assertSame([], $this->openDays());
    }

    public function test_inactive_spaces_and_past_days_do_not_open_a_date(): void
    {
        $hidden = $this->space('hidden', active: false);
        $studio = $this->space('studio');
        $this->day($hidden, 2);
        $this->day($studio, -1);

        $this->assertSame([], $this->openDays());
    }

    public function test_days_are_listed_once_and_in_date_order_with_the_guest_time_zone_today(): void
    {
        $studio = $this->space('studio');
        $suite = $this->space('suite');
        $this->day($studio, 5);
        $this->day($suite, 5);
        $this->day($studio, 2);

        $response = $this->getJson('/demo/reservation/availability')->assertOk();

        $this->assertSame([$this->today->addDays(2)->toDateString(), $this->today->addDays(5)->toDateString()], $response->json('dates'));
        $this->assertSame($this->today->toDateString(), $response->json('today'));
    }

    public function test_days_beyond_the_look_ahead_window_are_left_out(): void
    {
        $studio = $this->space('studio');
        $this->day($studio, 366);
        $this->day($studio, 367);

        $this->assertSame([$this->today->addDays(366)->toDateString()], $this->openDays());
    }

    public function test_another_venues_inventory_is_never_listed(): void
    {
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $foreign = $other->spaces()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'layouts' => ['banquet' => 10], 'is_active' => true]);
        $this->day($foreign, 2);

        $this->assertSame([], $this->openDays());
    }

    public function test_an_unpublished_or_unknown_venue_returns_404(): void
    {
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->getJson('/draft/reservation/availability')->assertNotFound();
        $this->getJson('/nowhere/reservation/availability')->assertNotFound();
    }
}
