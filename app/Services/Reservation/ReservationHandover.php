<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Venue;
use App\Notifications\GuestBookingSummary;

/**
 * Builds the WhatsApp / phone / email hand-over links that carry an
 * event request summary (or a plain "talk to staff" request) to the venue team.
 */
class ReservationHandover
{
    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    public function forBooking(Venue $venue, Booking $booking, string $locale): array
    {
        $message = $this->reservationMessage($booking, $locale);

        return $this->links($venue, $message, $this->subject('reservation', $locale).' '.$booking->reference);
    }

    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    public function forStaff(Venue $venue, string $locale): array
    {
        return $this->links($venue, $this->subject('staff_message', $locale), $this->subject('staff', $locale));
    }

    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    private function links(Venue $venue, string $message, string $subject): array
    {
        $whatsapp = preg_replace('/\D+/', '', (string) $venue->whatsapp);
        $phone = preg_replace('/[^\d+]/', '', (string) $venue->phone);

        return [
            'whatsapp_url' => $whatsapp !== '' ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($message) : null,
            'phone_url' => $phone !== '' ? 'tel:'.$phone : null,
            'email_url' => filled($venue->email) ? 'mailto:'.$venue->email.'?subject='.rawurlencode($subject).'&body='.rawurlencode($message) : null,
        ];
    }

    private function reservationMessage(Booking $booking, string $locale): string
    {
        $booking->loadMissing('space');

        $labels = match ($locale) {
            'en' => ['Hello, I would like to request an event at the venue.', 'Name', 'Event', 'Date', 'Guests', 'Setup', 'Space', 'Special request', 'Reference'],
            'ja' => ['こんにちは。会場でのイベント開催をリクエストしたいです。', 'お名前', 'イベント', '開催日', '人数', 'レイアウト', '会場', 'ご要望', '予約番号'],
            default => ['Halo, saya ingin mengajukan permintaan acara di venue.', 'Nama', 'Acara', 'Tanggal', 'Jumlah tamu', 'Susunan', 'Ruang', 'Permintaan khusus', 'Referensi'],
        };

        $format = fn ($date) => $date->locale($locale)->translatedFormat('j F Y');
        $dates = $booking->days() === 1
            ? $format($booking->event_start)
            : $format($booking->event_start).' → '.$format($booking->event_end);

        $lines = [
            $labels[0],
            '',
            "{$labels[1]}: {$booking->guest_name}",
            "{$labels[2]}: ".GuestBookingSummary::eventTypeName($booking->event_type, $locale),
            "{$labels[3]}: {$dates}",
            "{$labels[4]}: {$booking->guests}",
        ];

        if ($booking->setup_style) {
            $lines[] = "{$labels[5]}: ".ucfirst($booking->setup_style);
        }

        return implode("\n", [
            ...$lines,
            "{$labels[6]}: ".$booking->space->translatedName($locale),
            "{$labels[7]}: ".($booking->notes ?: '-'),
            '',
            "{$labels[8]}: {$booking->reference}",
        ]);
    }

    private function subject(string $key, string $locale): string
    {
        return [
            'en' => ['reservation' => 'Event request', 'staff' => 'Question for the events team', 'staff_message' => 'Hello, I would like to speak with the events team.'],
            'ja' => ['reservation' => 'イベントのご依頼', 'staff' => 'イベントチームへのご相談', 'staff_message' => 'こんにちは。イベントチームとお話ししたいです。'],
            'id' => ['reservation' => 'Permintaan acara', 'staff' => 'Pertanyaan untuk tim event', 'staff_message' => 'Halo, saya ingin bicara dengan tim event.'],
        ][$locale][$key];
    }
}
