<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference',
    'venue_id',
    'space_id',
    'conversation_id',
    'guest_name',
    'guest_email',
    'guest_phone',
    'contact_type',
    'locale',
    'event_type',
    'event_start',
    'event_end',
    'guests',
    'setup_style',
    'catering',
    'total_price',
    'status',
    'notes',
])]
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const EVENT_TYPES = ['wedding', 'corporate', 'conference', 'gala', 'birthday', 'exhibition', 'social'];

    /**
     * Which status a booking may move to from its current one. Cancelled is
     * final: its dates went back to the inventory and are not held any more.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_CANCELLED],
        self::STATUS_CANCELLED => [],
    ];

    protected function casts(): array
    {
        return [
            'event_start' => 'date',
            'event_end' => 'date',
            'guests' => 'integer',
            'catering' => 'boolean',
            'total_price' => 'decimal:2',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class)->withTrashed();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * A short, unambiguous, non-sequential code guests can quote to staff.
     */
    public static function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $reference = 'EV-'.collect(range(1, 6))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Event days, counting both the first and the last.
     */
    public function days(): int
    {
        return (int) $this->event_start->diffInDays($this->event_end) + 1;
    }
}
