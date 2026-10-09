<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a guest that the team confirmed or cancelled their event request.
 */
class BookingStatusChanged extends Notification implements ShouldQueue
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
        $locale = $booking->locale ?? $booking->venue->default_locale;
        $copy = $this->copy($locale)[$booking->status];

        return (new MailMessage)
            ->subject(sprintf($copy['subject'], $booking->reference))
            ->greeting(sprintf($copy['greeting'], $booking->guest_name))
            ->line(sprintf($copy['intro'], $booking->venue->name))
            ->lines(GuestBookingSummary::lines($booking, $locale))
            ->line($copy['next'])
            ->salutation($booking->venue->name);
    }

    /**
     * @return array<string, array{subject: string, greeting: string, intro: string, next: string}>
     */
    private function copy(string $locale): array
    {
        return [
            'en' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => 'Your event %s is confirmed',
                    'greeting' => 'Hello %s,',
                    'intro' => 'Good news: %s confirmed your event booking.',
                    'next' => 'Our events team will contact you about the deposit, the run of show and load-in details.',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => 'Your event request %s was cancelled',
                    'greeting' => 'Hello %s,',
                    'intro' => 'We are sorry: %s could not keep this event request.',
                    'next' => 'Please contact us if you would like other dates or another space for your event.',
                ],
            ],
            'ja' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => 'イベントのご予約が確定しました %s',
                    'greeting' => '%s 様',
                    'intro' => '%s がイベントのご予約を確定しました。',
                    'next' => '手付金、進行表、搬入のご案内について、イベントチームよりご連絡します。',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => 'イベントのご依頼はキャンセルされました %s',
                    'greeting' => '%s 様',
                    'intro' => '申し訳ありません。%s ではこのご依頼をお受けできませんでした。',
                    'next' => '別の日程や会場をご希望の場合はお問い合わせください。',
                ],
            ],
            'id' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => 'Acara %s dikonfirmasi',
                    'greeting' => 'Halo %s,',
                    'intro' => 'Kabar baik: %s telah mengonfirmasi booking acara Anda.',
                    'next' => 'Tim event kami akan menghubungi Anda soal uang muka, rundown, dan detail load-in.',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => 'Permintaan acara %s dibatalkan',
                    'greeting' => 'Halo %s,',
                    'intro' => 'Mohon maaf, %s tidak dapat melanjutkan permintaan acara ini.',
                    'next' => 'Hubungi kami bila Anda ingin tanggal atau ruang lain untuk acara Anda.',
                ],
            ],
        ][$locale] ?? $this->copy('en');
    }
}
