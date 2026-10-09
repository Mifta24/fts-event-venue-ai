<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the venue team that a guest asked to book a space for an event.
 */
class NewBookingRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['venue', 'space']);
        $contact = $booking->guest_phone ?? $booking->guest_email;

        $guests = number_format($booking->guests, 0, ',', '.');
        $dates = $booking->days() === 1
            ? $booking->event_start->toFormattedDateString()
            : "{$booking->event_start->toFormattedDateString()} to {$booking->event_end->toFormattedDateString()} ({$booking->days()} days)";

        return (new MailMessage)
            ->subject("New event request {$booking->reference} from {$booking->guest_name}")
            ->greeting("New event request for {$booking->venue->name}")
            ->line("{$booking->guest_name} asked for {$booking->space->name} for a ".str_replace('_', ' ', $booking->event_type).' event.')
            ->line("Date: {$dates}.")
            ->line("Guests: {$guests}".($booking->setup_style ? ", {$booking->setup_style} setup" : '').($booking->catering ? ', with catering' : '').'.')
            ->line("Total: {$booking->venue->currency} ".number_format((float) $booking->total_price, 0, ',', '.').'.')
            ->line("Contact ({$booking->contact_type}): {$contact}")
            ->when($booking->notes, fn (MailMessage $mail) => $mail->line("Note: {$booking->notes}"))
            ->action('Review the request', route('admin.bookings.index', ['status' => Booking::STATUS_PENDING]));
    }
}
