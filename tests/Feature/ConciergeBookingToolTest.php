<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\UnitInventory;
use App\Services\Concierge\ApartmentConciergeTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConciergeBookingToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_booking_tool_creates_a_reference_and_holds_the_requested_units(): void
    {
        $apartment = Apartment::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $unit = $apartment->unitTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 1000000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $conversation = Conversation::create(['apartment_id' => $apartment->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $checkIn = CarbonImmutable::now()->addDays(5);
        foreach ([0, 1] as $offset) {
            UnitInventory::create(['unit_type_id' => $unit->id, 'stay_date' => $checkIn->addDays($offset)->toDateString(), 'total_units' => 3, 'booked_units' => 0, 'price' => 1000000]);
        }

        $tools = new ApartmentConciergeTools($apartment, $conversation, 'en');
        $input = [
            'unit_type_slug' => 'deluxe-king', 'check_in' => $checkIn->toDateString(), 'check_out' => $checkIn->addDays(2)->toDateString(),
            'adults' => 2, 'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222',
        ];

        $quote = $tools->dispatch('check_availability', [...$input, 'extra_bed' => false]);
        $this->assertSame(2000000.0, $quote['ui']['quote']['grand_total']);

        $result = $tools->dispatch('create_booking_request', [...$input, 'units' => 2]);

        $booking = Booking::firstOrFail();
        $this->assertSame($booking->reference, $result['ui']['booking']['booking_reference']);
        $this->assertSame(2, $booking->unit_count);
        $this->assertSame(4000000.0, (float) $booking->total_price);
        $this->assertSame([2, 2], UnitInventory::orderBy('stay_date')->pluck('booked_units')->all());

        $tooMany = $tools->dispatch('create_booking_request', [...$input, 'units' => 2]);
        $this->assertNull($tooMany['ui']);
        $this->assertSame(1, Booking::count());
    }

    public function test_ai_tools_quote_long_stays_with_the_discount_and_filter_by_bedrooms(): void
    {
        $apartment = Apartment::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'weekly_discount_percent' => 10, 'monthly_discount_percent' => 25]);
        $studio = $apartment->unitTypes()->create(['name' => 'Studio', 'slug' => 'studio', 'base_price' => 600000, 'bedrooms' => 0, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $family = $apartment->unitTypes()->create(['name' => 'Two Bedroom', 'slug' => 'two-bedroom', 'base_price' => 1500000, 'bedrooms' => 2, 'bathrooms' => 2, 'floor_range' => '15–26', 'max_adults' => 4, 'max_children' => 2, 'is_active' => true]);
        $conversation = Conversation::create(['apartment_id' => $apartment->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $checkIn = CarbonImmutable::now()->addDays(5)->startOfDay();
        foreach ([$studio, $family] as $unit) {
            foreach (range(0, 6) as $offset) {
                UnitInventory::create(['unit_type_id' => $unit->id, 'stay_date' => $checkIn->addDays($offset)->toDateString(), 'total_units' => 2, 'booked_units' => 0, 'price' => $unit->base_price]);
            }
        }

        $tools = new ApartmentConciergeTools($apartment, $conversation, 'en');
        $week = ['check_in' => $checkIn->toDateString(), 'check_out' => $checkIn->addDays(7)->toDateString()];

        $quote = $tools->dispatch('check_availability', [...$week, 'unit_type_slug' => 'two-bedroom'])['ui']['quote'];
        $this->assertSame(10, $quote['discount_percent']);
        $this->assertSame(10500000.0, $quote['subtotal']);
        $this->assertSame(9450000.0, $quote['grand_total']);

        $search = $tools->dispatch('search_units', [...$week, 'adults' => 2, 'bedrooms' => 1])['ui']['units'];
        $this->assertSame(['two-bedroom'], array_column($search, 'unit_type_slug'));
        $this->assertSame(9450000.0, $search[0]['total_price']);
        $this->assertSame('15–26', $search[0]['floor_range']);

        $detail = json_decode($tools->dispatch('get_unit_detail', ['unit_type_slug' => 'studio'])['text'], true);
        $this->assertSame(0, $detail['bedrooms']);
        $this->assertSame(25, $detail['long_stay']['monthly_discount_percent']);
    }
}
