<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Space;
use App\Models\SpaceInventory;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\BookingRequestReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingRequest;
use App\Notifications\NewHandoverRequest;
use App\Services\Planner\VenuePlannerTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffAndGuestNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private Space $space;

    private User $owner;

    private User $inactiveStaff;

    private CarbonImmutable $eventStart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'en']);
        $this->space = $this->venue->spaces()->create(['name' => 'Studio', 'slug' => 'studio', 'translations' => ['ja' => ['name' => 'スタジオ']], 'base_price' => 500000, 'layouts' => ['banquet' => 100], 'is_active' => true]);

        $this->owner = User::factory()->create();
        $this->inactiveStaff = User::factory()->create();
        $this->venue->users()->attach($this->owner->id, ['role' => 'owner', 'status' => 'active']);
        $this->venue->users()->attach($this->inactiveStaff->id, ['role' => 'staff', 'status' => 'suspended']);

        $this->eventStart = CarbonImmutable::now($this->venue->timezone)->addDays(5)->startOfDay();
        foreach ([0, 1] as $offset) {
            SpaceInventory::create(['space_id' => $this->space->id, 'event_date' => $this->eventStart->addDays($offset)->toDateString(), 'total_units' => 2, 'booked_units' => 0, 'price' => 500000]);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reservation(array $overrides = []): array
    {
        return [
            'space_slug' => 'studio', 'event_start' => $this->eventStart->toDateString(), 'event_end' => $this->eventStart->addDay()->toDateString(),
            'event_type' => 'wedding', 'guests' => 80, 'setup_style' => 'banquet', 'locale' => 'id', 'guest_name' => 'Ayu', 'contact_type' => 'email', 'contact_value' => 'ayu@example.test',
            ...$overrides,
        ];
    }

    public function test_an_event_request_notifies_active_staff_and_acknowledges_a_guest_who_left_an_email(): void
    {
        Notification::fake();

        $this->postJson('/demo/reservation', $this->reservation())->assertCreated();

        $booking = Booking::firstOrFail();
        Notification::assertSentTo($this->owner, NewBookingRequest::class, fn ($notification) => $notification->booking->is($booking));
        Notification::assertNotSentTo($this->inactiveStaff, NewBookingRequest::class);
        Notification::assertSentOnDemand(BookingRequestReceived::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'ayu@example.test');
        $this->assertSame('id', $booking->locale);
    }

    public function test_a_guest_who_chose_whatsapp_gets_no_email_but_staff_are_still_told(): void
    {
        Notification::fake();

        $this->postJson('/demo/reservation', $this->reservation(['contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 1111']))->assertCreated();

        Notification::assertSentTo($this->owner, NewBookingRequest::class);
        Notification::assertNothingSentTo(new AnonymousNotifiable);
        Notification::assertSentOnDemandTimes(BookingRequestReceived::class, 0);
    }

    public function test_a_request_that_does_not_fit_the_space_notifies_nobody(): void
    {
        Notification::fake();

        $this->postJson('/demo/reservation', $this->reservation(['guests' => 400]))->assertUnprocessable();

        Notification::assertNothingSent();
    }

    public function test_the_planner_booking_tool_notifies_staff_and_remembers_the_guest_language(): void
    {
        Notification::fake();
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'ja']);

        (new VenuePlannerTools($this->venue, $conversation, 'ja'))->dispatch('create_booking_request', [
            'space_slug' => 'studio', 'event_start' => $this->eventStart->toDateString(), 'event_end' => $this->eventStart->addDay()->toDateString(),
            'event_type' => 'corporate', 'guests' => 80, 'guest_name' => 'Aki', 'guest_phone' => '+81 90 0000 0000', 'guest_email' => 'aki@example.test',
        ]);

        Notification::assertSentTo($this->owner, NewBookingRequest::class);
        Notification::assertSentOnDemand(BookingRequestReceived::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'aki@example.test');
        $this->assertSame('ja', Booking::firstOrFail()->locale);
    }

    public function test_a_handover_notifies_active_staff_with_a_link_to_the_conversation(): void
    {
        Notification::fake();
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        (new VenuePlannerTools($this->venue, $conversation, 'en'))->dispatch('request_human_handover', ['reason' => HandoverRequest::REASON_COMPLAINT, 'summary' => 'Noisy neighbours']);

        $handover = HandoverRequest::firstOrFail();
        Notification::assertSentTo($this->owner, NewHandoverRequest::class, fn ($notification) => $notification->handover->is($handover));
        Notification::assertNotSentTo($this->inactiveStaff, NewHandoverRequest::class);

        $mail = (new NewHandoverRequest($handover))->toMail($this->owner);
        $this->assertStringContainsString('Noisy neighbours', implode(' ', $mail->introLines));
        $this->assertSame(route('admin.handovers.show', $handover), $mail->actionUrl);
    }

    public function test_the_guest_emails_are_written_in_the_language_the_guest_used(): void
    {
        $booking = Booking::create([
            'reference' => 'EV-TEST01', 'venue_id' => $this->venue->id, 'space_id' => $this->space->id, 'guest_name' => 'Aki',
            'guest_email' => 'aki@example.test', 'event_type' => 'wedding', 'guests' => 80, 'event_start' => $this->eventStart, 'event_end' => $this->eventStart->addDay(), 'total_price' => 1000000,
            'status' => Booking::STATUS_CONFIRMED, 'locale' => 'ja',
        ]);

        $confirmed = (new BookingStatusChanged($booking))->toMail(new AnonymousNotifiable);
        $this->assertSame('イベントのご予約が確定しました EV-TEST01', $confirmed->subject);
        $this->assertContains('会場: スタジオ', $confirmed->introLines);
        $this->assertContains('イベント: 結婚式', $confirmed->introLines);
        $this->assertContains('開催日: '.$this->eventStart->year.'年'.$this->eventStart->month.'月'.$this->eventStart->day.'日 → '.$this->eventStart->addDay()->year.'年'.$this->eventStart->addDay()->month.'月'.$this->eventStart->addDay()->day.'日', $confirmed->introLines);

        $booking->update(['status' => Booking::STATUS_CANCELLED, 'locale' => 'id']);
        $this->assertSame('Permintaan acara EV-TEST01 dibatalkan', (new BookingStatusChanged($booking))->toMail(new AnonymousNotifiable)->subject);

        $booking->update(['locale' => null]);
        $this->assertSame('Your event request EV-TEST01 was cancelled', (new BookingStatusChanged($booking))->toMail(new AnonymousNotifiable)->subject);
    }
}
