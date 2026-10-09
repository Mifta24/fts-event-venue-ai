<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'venue_id',
    'name',
    'slug',
    'description',
    'translations',
    'space_type',
    'size_sqm',
    'ceiling_height_m',
    'level_label',
    'layouts',
    'av_included',
    'catering_available',
    'catering_price',
    'base_price',
    'amenities',
    'is_active',
    'sort_order',
])]
class Space extends Model
{
    use HasFactory, SoftDeletes;

    /** How a room can be set up; each style seats a different number of guests. */
    public const LAYOUTS = ['banquet', 'theatre', 'classroom', 'boardroom', 'cocktail'];

    public const TYPES = ['indoor', 'semi_outdoor', 'outdoor'];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'layouts' => 'array',
            'amenities' => 'array',
            'ceiling_height_m' => 'decimal:1',
            'av_included' => 'boolean',
            'catering_available' => 'boolean',
            'catering_price' => 'decimal:2',
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(SpaceImage::class)->orderBy('sort_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(SpaceInventory::class);
    }

    /**
     * The most guests this space holds in any setup.
     */
    public function maxGuests(): int
    {
        return (int) max([0, ...array_values($this->layouts ?? [])]);
    }

    /**
     * How many guests the given setup seats, or null when the space cannot
     * be set up that way.
     */
    public function capacityFor(string $layout): ?int
    {
        $capacity = $this->layouts[$layout] ?? null;

        return $capacity === null ? null : (int) $capacity;
    }

    public function isOutdoor(): bool
    {
        return $this->space_type !== 'indoor';
    }

    public function translatedName(string $locale): string
    {
        $value = $this->translations[$locale]['name'] ?? null;

        return filled($value) ? $value : $this->name;
    }

    public function translatedDescription(string $locale): ?string
    {
        $value = $this->translations[$locale]['description'] ?? null;

        return filled($value) ? $value : $this->description;
    }
}
