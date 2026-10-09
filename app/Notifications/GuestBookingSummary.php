<?php

namespace App\Notifications;

use App\Models\Booking;

/**
 * The few lines every guest email repeats about the event, in the guest's language.
 */
class GuestBookingSummary
{
    /**
     * @return list<string>
     */
    public static function lines(Booking $booking, string $locale): array
    {
        $booking->loadMissing(['venue', 'space']);

        $labels = match ($locale) {
            'ja' => ['予約番号', '会場', '開催日', 'イベント', '人数', '合計'],
            'id' => ['Referensi', 'Ruang', 'Tanggal acara', 'Jenis acara', 'Tamu', 'Total'],
            default => ['Reference', 'Space', 'Event date', 'Event type', 'Guests', 'Total'],
        };

        $dateFormat = $locale === 'ja' ? 'Y年n月j日' : 'j F Y';
        $format = fn ($date) => $date->locale($locale)->translatedFormat($dateFormat);
        $dates = $booking->days() === 1
            ? $format($booking->event_start)
            : $format($booking->event_start).' → '.$format($booking->event_end);

        return [
            "{$labels[0]}: {$booking->reference}",
            "{$labels[1]}: {$booking->space->translatedName($locale)}",
            "{$labels[2]}: {$dates}",
            "{$labels[3]}: ".self::eventTypeName($booking->event_type, $locale),
            "{$labels[4]}: {$booking->guests}",
            "{$labels[5]}: {$booking->venue->currency} ".number_format((float) $booking->total_price, 0, ',', '.'),
        ];
    }

    /**
     * The guest-facing name of a coded event type.
     */
    public static function eventTypeName(string $type, string $locale): string
    {
        $names = [
            'en' => ['wedding' => 'Wedding', 'corporate' => 'Corporate event', 'conference' => 'Conference', 'gala' => 'Gala dinner', 'birthday' => 'Birthday party', 'exhibition' => 'Exhibition', 'social' => 'Social gathering'],
            'id' => ['wedding' => 'Pernikahan', 'corporate' => 'Acara perusahaan', 'conference' => 'Konferensi', 'gala' => 'Gala dinner', 'birthday' => 'Pesta ulang tahun', 'exhibition' => 'Pameran', 'social' => 'Acara sosial'],
            'ja' => ['wedding' => '結婚式', 'corporate' => '企業イベント', 'conference' => 'カンファレンス', 'gala' => 'ガラディナー', 'birthday' => 'バースデーパーティー', 'exhibition' => '展示会', 'social' => '懇親会'],
        ];

        return $names[$locale][$type] ?? $names['en'][$type] ?? $type;
    }
}
