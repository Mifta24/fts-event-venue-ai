<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a guest that the team confirmed or cancelled their stay request.
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
        $booking = $this->booking->loadMissing(['apartment', 'unitType']);
        $locale = $booking->locale ?? $booking->apartment->default_locale;
        $copy = $this->copy($locale)[$booking->status];

        return (new MailMessage)
            ->subject(sprintf($copy['subject'], $booking->reference))
            ->greeting(sprintf($copy['greeting'], $booking->guest_name))
            ->line(sprintf($copy['intro'], $booking->apartment->name))
            ->lines(GuestBookingSummary::lines($booking, $locale))
            ->line($copy['next'])
            ->salutation($booking->apartment->name);
    }

    /**
     * @return array<string, array{subject: string, greeting: string, intro: string, next: string}>
     */
    private function copy(string $locale): array
    {
        return [
            'en' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => 'Your stay %s is confirmed',
                    'greeting' => 'Hello %s,',
                    'intro' => 'Good news: %s confirmed your stay request.',
                    'next' => 'Our team will contact you about move-in details and payment.',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => 'Your stay request %s was cancelled',
                    'greeting' => 'Hello %s,',
                    'intro' => 'We are sorry: %s could not keep this stay request.',
                    'next' => 'Please contact us if you would like other dates or another unit.',
                ],
            ],
            'ja' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => 'ご入居が確定しました %s',
                    'greeting' => '%s 様',
                    'intro' => '%s がリクエストを確定しました。',
                    'next' => '入居の詳細とお支払いについて、スタッフよりご連絡します。',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => '入居リクエストはキャンセルされました %s',
                    'greeting' => '%s 様',
                    'intro' => '申し訳ありません。%s ではこのリクエストをお受けできませんでした。',
                    'next' => '別の日程やお部屋をご希望の場合はお問い合わせください。',
                ],
            ],
            'id' => [
                Booking::STATUS_CONFIRMED => [
                    'subject' => 'Sewa %s dikonfirmasi',
                    'greeting' => 'Halo %s,',
                    'intro' => 'Kabar baik: %s telah mengonfirmasi permintaan sewa Anda.',
                    'next' => 'Tim kami akan menghubungi Anda soal detail masuk unit dan pembayaran.',
                ],
                Booking::STATUS_CANCELLED => [
                    'subject' => 'Permintaan sewa %s dibatalkan',
                    'greeting' => 'Halo %s,',
                    'intro' => 'Mohon maaf, %s tidak dapat melanjutkan permintaan sewa ini.',
                    'next' => 'Hubungi kami bila Anda ingin tanggal atau unit lain.',
                ],
            ],
        ][$locale] ?? $this->copy('en');
    }
}
