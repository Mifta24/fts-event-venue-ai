<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a guest their event request arrived. It is not a confirmation yet.
 */
class BookingRequestReceived extends Notification implements ShouldQueue
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
        $copy = $this->copy($locale);
        $summary = GuestBookingSummary::lines($booking, $locale);

        return (new MailMessage)
            ->subject(sprintf($copy['subject'], $booking->reference))
            ->greeting(sprintf($copy['greeting'], $booking->guest_name))
            ->line(sprintf($copy['intro'], $booking->venue->name))
            ->lines($summary)
            ->line($copy['next'])
            ->salutation($booking->venue->name);
    }

    /**
     * @return array{subject: string, greeting: string, intro: string, next: string}
     */
    private function copy(string $locale): array
    {
        return [
            'en' => [
                'subject' => 'We received your event request %s',
                'greeting' => 'Hello %s,',
                'intro' => 'Thank you for your event request at %s. Our events team will check the date and reply shortly. This is not a confirmation yet.',
                'next' => 'Keep your reference handy if you contact us.',
            ],
            'ja' => [
                'subject' => 'イベントのご依頼を受け付けました %s',
                'greeting' => '%s 様',
                'intro' => '%s へのイベントのご依頼ありがとうございます。イベントチームが日程を確認し、まもなくご連絡します。まだ確定ではありません。',
                'next' => 'お問い合わせの際は予約番号をお知らせください。',
            ],
            'id' => [
                'subject' => 'Permintaan acara %s kami terima',
                'greeting' => 'Halo %s,',
                'intro' => 'Terima kasih atas permintaan acara Anda di %s. Tim event kami akan memeriksa tanggalnya dan segera membalas. Ini belum merupakan konfirmasi.',
                'next' => 'Simpan nomor referensi ini bila Anda menghubungi kami.',
            ],
        ][$locale] ?? $this->copy('en');
    }
}
