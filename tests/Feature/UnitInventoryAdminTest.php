<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\UnitInventory;
use App\Models\UnitType;
use App\Models\User;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UnitInventoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private Apartment $apartment;

    private UnitType $unitType;

    private User $staff;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apartment = Apartment::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->unitType = $this->apartment->unitTypes()->create(['name' => 'Studio', 'slug' => 'studio', 'base_price' => 500000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);

        $this->staff = User::factory()->create();
        $this->apartment->users()->attach($this->staff->id, ['role' => 'staff', 'status' => 'active']);

        $this->today = CarbonImmutable::now($this->apartment->timezone)->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function range(array $overrides = []): array
    {
        return ['from' => $this->today->addDays(2)->toDateString(), 'to' => $this->today->addDays(4)->toDateString(), 'total_units' => 3, 'price' => 750000, ...$overrides];
    }

    private function save(array $payload, ?UnitType $unitType = null)
    {
        return $this->actingAs($this->staff)->post(route('admin.unit-types.inventory.store', $unitType ?? $this->unitType), $payload);
    }

    public function test_visitors_are_sent_to_the_login_page(): void
    {
        $this->get(route('admin.unit-types.inventory.index', $this->unitType))->assertRedirect(route('admin.login'));
        $this->post(route('admin.unit-types.inventory.store', $this->unitType), $this->range())->assertRedirect(route('admin.login'));
    }

    public function test_opening_a_range_creates_every_night_with_the_given_units_and_price(): void
    {
        $this->save($this->range())->assertRedirect()->assertSessionHas('status');

        $nights = $this->unitType->inventory()->orderBy('stay_date')->get();
        $this->assertCount(3, $nights);
        $this->assertSame([3, 3, 3], $nights->pluck('total_units')->all());
        $this->assertSame([0, 0, 0], $nights->pluck('booked_units')->all());
        $this->assertSame([750000.0, 750000.0, 750000.0], $nights->map(fn ($night) => (float) $night->price)->all());
    }

    public function test_a_blank_price_gives_new_nights_the_base_price_and_keeps_existing_prices(): void
    {
        $this->save($this->range(['to' => $this->today->addDays(2)->toDateString()]));

        $this->save($this->range(['price' => null, 'total_units' => 5]));

        $prices = $this->unitType->inventory()->orderBy('stay_date')->get()->map(fn ($night) => (float) $night->price)->all();
        $this->assertSame([750000.0, 500000.0, 500000.0], $prices);
        $this->assertSame([5, 5, 5], $this->unitType->inventory()->orderBy('stay_date')->pluck('total_units')->all());
    }

    public function test_saving_again_does_not_duplicate_nights_or_touch_booked_units(): void
    {
        $this->save($this->range());
        $this->unitType->inventory()->update(['booked_units' => 2]);

        $this->save($this->range(['total_units' => 4, 'price' => 800000]))->assertSessionHas('status');

        $this->assertSame(3, $this->unitType->inventory()->count());
        $this->assertSame([2, 2, 2], $this->unitType->inventory()->pluck('booked_units')->all());
        $this->assertSame([4, 4, 4], $this->unitType->inventory()->pluck('total_units')->all());
    }

    public function test_units_for_rent_cannot_drop_below_what_is_already_booked(): void
    {
        $this->save($this->range());
        $this->unitType->inventory()->orderBy('stay_date')->first()->update(['booked_units' => 3]);

        $this->save($this->range(['total_units' => 2, 'price' => 1]))->assertSessionHasErrors('total_units');

        $this->assertSame([3, 3, 3], $this->unitType->inventory()->pluck('total_units')->all());
        $this->assertSame([750000.0, 750000.0, 750000.0], $this->unitType->inventory()->get()->map(fn ($night) => (float) $night->price)->all());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidRanges(): array
    {
        return [
            'starts in the past' => [['from' => '2000-01-01', 'to' => '2000-01-05'], 'from'],
            'ends before it starts' => [['to' => 'before-from'], 'to'],
            'spans more than a year' => [['to' => 'far-future'], 'to'],
            'negative price' => [['price' => -1], 'price'],
            'negative units' => [['total_units' => -1], 'total_units'],
            'missing dates' => [['from' => null, 'to' => null], 'from'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidRanges')]
    public function test_an_invalid_range_is_rejected_and_nothing_is_saved(array $overrides, string $field): void
    {
        $overrides = array_map(fn ($value) => match ($value) {
            'before-from' => $this->today->addDay()->toDateString(),
            'far-future' => $this->today->addDays(2 + 366)->toDateString(),
            default => $value,
        }, $overrides);

        $this->save($this->range($overrides))->assertSessionHasErrors($field);

        $this->assertSame(0, UnitInventory::count());
    }

    public function test_a_range_of_exactly_one_year_is_accepted(): void
    {
        $this->save($this->range(['from' => $this->today->toDateString(), 'to' => $this->today->addDays(365)->toDateString()]))->assertSessionHas('status');

        $this->assertSame(366, $this->unitType->inventory()->count());
    }

    public function test_the_month_calendar_shows_free_units_and_price_for_open_nights_and_marks_the_rest_as_closed(): void
    {
        $day = $this->today->addDays(2);
        $closedDay = $day->day < $day->daysInMonth ? $day->addDay() : $day->subDay();
        $this->save($this->range(['from' => $day->toDateString(), 'to' => $day->toDateString(), 'total_units' => 4, 'price' => 750000]));
        $this->unitType->inventory()->update(['booked_units' => 1]);

        $this->actingAs($this->staff)
            ->get(route('admin.unit-types.inventory.index', [$this->unitType, 'month' => $day->format('Y-m')]))
            ->assertOk()
            ->assertSee('data-day="'.$day->toDateString().'"', false)
            ->assertSee($day->format('l j F').': 3 of 4 free', false)
            ->assertSee('750.000')
            ->assertSee($closedDay->format('l j F').': not open for rent', false);
    }

    public function test_a_malformed_month_falls_back_to_the_current_month(): void
    {
        $this->actingAs($this->staff)
            ->get(route('admin.unit-types.inventory.index', [$this->unitType, 'month' => 'banana']))
            ->assertOk()
            ->assertSee($this->today->format('F Y'));
    }

    public function test_new_inventory_is_what_the_reservation_quote_uses(): void
    {
        $this->save($this->range(['from' => $this->today->addDays(2)->toDateString(), 'to' => $this->today->addDays(3)->toDateString(), 'total_units' => 1, 'price' => 650000]));

        $quote = app(ReservationService::class)->quote($this->unitType, $this->today->addDays(2), $this->today->addDays(4));

        $this->assertSame(1300000.0, $quote['grand_total']);
    }

    public function test_staff_cannot_read_or_change_another_apartments_inventory(): void
    {
        $other = Apartment::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $foreign = $other->unitTypes()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'max_adults' => 1, 'max_children' => 0, 'is_active' => true]);

        $this->actingAs($this->staff)->get(route('admin.unit-types.inventory.index', $foreign))->assertNotFound();
        $this->save($this->range(), $foreign)->assertNotFound();

        $this->assertSame(0, UnitInventory::count());
    }
}
