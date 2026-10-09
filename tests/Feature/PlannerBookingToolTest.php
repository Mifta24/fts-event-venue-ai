<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\SpaceInventory;
use App\Models\Venue;
use App\Services\Planner\VenuePlannerTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlannerBookingToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_booking_tool_creates_a_reference_and_holds_the_event_days(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $space = $venue->spaces()->create(['name' => 'Grand Hall', 'slug' => 'grand-hall', 'base_price' => 10000000, 'layouts' => ['banquet' => 200, 'theatre' => 300], 'catering_available' => true, 'catering_price' => 100000, 'is_active' => true]);
        $conversation = Conversation::create(['venue_id' => $venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $start = CarbonImmutable::now()->addDays(5);
        foreach ([0, 1] as $offset) {
            SpaceInventory::create(['space_id' => $space->id, 'event_date' => $start->addDays($offset)->toDateString(), 'total_units' => 1, 'booked_units' => 0, 'price' => 10000000]);
        }

        $tools = new VenuePlannerTools($venue, $conversation, 'en');
        $input = [
            'space_slug' => 'grand-hall', 'event_start' => $start->toDateString(), 'event_end' => $start->addDay()->toDateString(),
            'event_type' => 'wedding', 'guests' => 150, 'setup_style' => 'banquet', 'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222',
        ];

        $quote = $tools->dispatch('check_availability', [...$input, 'catering' => true]);
        $this->assertSame(2, $quote['ui']['quote']['days']);
        $this->assertSame(20000000.0, $quote['ui']['quote']['space_total']);
        $this->assertSame(30000000.0, $quote['ui']['quote']['catering_total']);

        $result = $tools->dispatch('create_booking_request', [...$input, 'catering' => true]);

        $booking = Booking::firstOrFail();
        $this->assertSame($booking->reference, $result['ui']['booking']['booking_reference']);
        $this->assertSame('wedding', $booking->event_type);
        $this->assertSame(2, $booking->days());
        $this->assertSame(50000000.0, (float) $booking->total_price);
        $this->assertSame([1, 1], SpaceInventory::orderBy('event_date')->pluck('booked_units')->all());

        $again = $tools->dispatch('create_booking_request', $input);
        $this->assertNull($again['ui']);
        $this->assertSame(1, Booking::count());
    }

    public function test_ai_booking_tool_refuses_a_headcount_the_setup_cannot_seat_and_events_over_the_online_limit(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $venue->spaces()->create(['name' => 'Small Hall', 'slug' => 'small-hall', 'base_price' => 1000000, 'layouts' => ['banquet' => 50], 'is_active' => true]);
        $conversation = Conversation::create(['venue_id' => $venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);
        $tools = new VenuePlannerTools($venue, $conversation, 'en');
        $start = CarbonImmutable::now()->addDays(5);

        $base = ['space_slug' => 'small-hall', 'event_start' => $start->toDateString(), 'event_end' => $start->toDateString(), 'event_type' => 'social', 'guests' => 80, 'setup_style' => 'banquet', 'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222'];

        $tooBig = $tools->dispatch('create_booking_request', $base);
        $this->assertNull($tooBig['ui']);
        $this->assertStringContainsString('does not seat', $tooBig['text']);

        $tooLong = $tools->dispatch('create_booking_request', [...$base, 'guests' => 20, 'event_end' => $start->addDays(20)->toDateString()]);
        $this->assertNull($tooLong['ui']);
        $this->assertStringContainsString('request_human_handover', $tooLong['text']);
        $this->assertSame(0, Booking::count());
    }

    public function test_ai_tools_quote_discounted_events_and_filter_by_capacity_and_setting(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'weekday_discount_percent' => 15, 'multiday_discount_percent' => 10]);
        $meeting = $venue->spaces()->create(['name' => 'Meeting Room', 'slug' => 'meeting-room', 'base_price' => 6000000, 'space_type' => 'indoor', 'layouts' => ['boardroom' => 20, 'theatre' => 40], 'is_active' => true]);
        $garden = $venue->spaces()->create(['name' => 'Garden', 'slug' => 'garden', 'base_price' => 15000000, 'space_type' => 'outdoor', 'level_label' => 'Ground', 'layouts' => ['banquet' => 300, 'cocktail' => 500], 'is_active' => true]);
        $conversation = Conversation::create(['venue_id' => $venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        // The next Monday, so three days are a Monday-to-Wednesday weekday event.
        $monday = CarbonImmutable::now()->next('monday')->startOfDay();
        foreach ([$meeting, $garden] as $space) {
            foreach (range(0, 2) as $offset) {
                SpaceInventory::create(['space_id' => $space->id, 'event_date' => $monday->addDays($offset)->toDateString(), 'total_units' => 1, 'booked_units' => 0, 'price' => $space->base_price]);
            }
        }

        $tools = new VenuePlannerTools($venue, $conversation, 'en');
        $event = ['event_start' => $monday->toDateString(), 'event_end' => $monday->addDays(2)->toDateString()];

        $quote = $tools->dispatch('check_availability', [...$event, 'space_slug' => 'garden'])['ui']['quote'];
        $this->assertSame(3, $quote['days']);
        $this->assertSame(15, $quote['discount_percent']);
        $this->assertSame(45000000.0, $quote['subtotal']);
        $this->assertSame(38250000.0, $quote['grand_total']);

        $search = $tools->dispatch('search_spaces', [...$event, 'guests' => 200, 'layout' => 'banquet'])['ui']['spaces'];
        $this->assertSame(['garden'], array_column($search, 'space_slug'));
        $this->assertSame(38250000.0, $search[0]['total_price']);
        $this->assertSame(300, $search[0]['capacity']);

        $indoor = $tools->dispatch('search_spaces', [...$event, 'guests' => 15, 'space_type' => 'indoor'])['ui']['spaces'];
        $this->assertSame(['meeting-room'], array_column($indoor, 'space_slug'));

        $detail = json_decode($tools->dispatch('get_space_detail', ['space_slug' => 'garden'])['text'], true);
        $this->assertSame(500, $detail['max_guests']);
        $this->assertSame('Ground', $detail['level_label']);
        $this->assertSame(10, $detail['discounts']['multiday_discount_percent']);
    }
}
