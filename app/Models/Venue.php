<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'custom_domain',
    'description',
    'translations',
    'address',
    'city',
    'country',
    'latitude',
    'longitude',
    'phone',
    'whatsapp',
    'email',
    'timezone',
    'currency',
    'default_locale',
    'load_in_time',
    'curfew_time',
    'weekday_discount_percent',
    'multiday_discount_percent',
    'logo_path',
    'cover_path',
    'public_status',
])]
class Venue extends Model
{
    use HasFactory, SoftDeletes;

    /** Events of at least this many days get the multi-day rate. */
    public const MULTIDAY_MIN_DAYS = 3;

    /** Weekday events run entirely on these ISO weekdays (Monday to Thursday). */
    public const WEEKDAY_ISO_DAYS = [1, 2, 3, 4];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'weekday_discount_percent' => 'integer',
            'multiday_discount_percent' => 'integer',
        ];
    }

    /**
     * The discount an event earns, in percent: the multi-day rate for events
     * of three days or more, the weekday rate for events held only Monday to
     * Thursday. When both apply the better one wins.
     */
    public function eventDiscountPercent(CarbonImmutable $start, int $days): int
    {
        $rates = [];

        if ($days >= self::MULTIDAY_MIN_DAYS) {
            $rates[] = $this->multiday_discount_percent;
        }

        if ($this->isWeekdayEvent($start, $days)) {
            $rates[] = $this->weekday_discount_percent;
        }

        return max([0, ...$rates]);
    }

    /**
     * Whether every day of the event falls between Monday and Thursday.
     */
    public function isWeekdayEvent(CarbonImmutable $start, int $days): bool
    {
        for ($offset = 0; $offset < $days; $offset++) {
            if (! in_array($start->addDays($offset)->dayOfWeekIso, self::WEEKDAY_ISO_DAYS, true)) {
                return false;
            }
        }

        return $days > 0;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'venue_users')
            ->using(VenueUser::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * Sends a notification to every active member of the venue team.
     */
    public function notifyStaff(Notification $notification): void
    {
        NotificationFacade::send($this->users()->wherePivot('status', 'active')->get(), $notification);
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(VenueKnowledgeItem::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function translatedDescription(string $locale): ?string
    {
        $value = $this->translations[$locale]['description'] ?? null;

        return filled($value) ? $value : $this->description;
    }

    public function isPublished(): bool
    {
        return $this->public_status === 'published';
    }

    public function publicUrl(): string
    {
        return $this->custom_domain
            ? 'https://'.$this->custom_domain
            : url('/'.$this->slug);
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'venue';
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()->where('slug', $slug)->exists() || in_array($slug, static::reservedSlugs(), true)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public static function reservedSlugs(): array
    {
        return [
            'admin', 'login', 'logout', 'register', 'pricing', 'dashboard',
            'api', 'terms', 'privacy', 'forgot-password', 'reset-password',
            'verify-email', 'confirm-password', 'storage', 'build', 'account',
        ];
    }
}
