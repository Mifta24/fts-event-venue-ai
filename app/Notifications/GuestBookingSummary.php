<?php

namespace App\Notifications;

use App\Models\Booking;

/**
 * The few lines every guest email repeats about the stay, in the guest's language.
 */
class GuestBookingSummary
{
    /**
     * @return list<string>
     */
    public static function lines(Booking $booking, string $locale): array
    {
        $booking->loadMissing(['apartment', 'unitType']);

        $labels = match ($locale) {
            'ja' => ['予約番号', 'お部屋', '入居日', '退去日', '人数', '合計'],
            'id' => ['Referensi', 'Unit', 'Check-in', 'Check-out', 'Penghuni', 'Total'],
            default => ['Reference', 'Unit', 'Move-in', 'Move-out', 'Residents', 'Total'],
        };

        $dateFormat = $locale === 'ja' ? 'Y年n月j日' : 'j F Y';
        $format = fn ($date) => $date->locale($locale)->translatedFormat($dateFormat);

        return [
            "{$labels[0]}: {$booking->reference}",
            "{$labels[1]}: {$booking->unit_count} × {$booking->unitType->translatedName($locale)}",
            "{$labels[2]}: {$format($booking->check_in)}",
            "{$labels[3]}: {$format($booking->check_out)}",
            "{$labels[4]}: ".($booking->adults + $booking->children),
            "{$labels[5]}: {$booking->apartment->currency} ".number_format((float) $booking->total_price, 0, ',', '.'),
        ];
    }
}
