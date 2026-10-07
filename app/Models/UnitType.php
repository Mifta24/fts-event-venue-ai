<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'apartment_id',
    'name',
    'slug',
    'description',
    'translations',
    'size_sqm',
    'bedrooms',
    'bathrooms',
    'floor_range',
    'max_adults',
    'max_children',
    'bed_config',
    'view_type',
    'breakfast_included',
    'extra_bed_available',
    'extra_bed_price',
    'base_price',
    'amenities',
    'is_active',
    'sort_order',
])]
class UnitType extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'bed_config' => 'array',
            'amenities' => 'array',
            'breakfast_included' => 'boolean',
            'extra_bed_available' => 'boolean',
            'extra_bed_price' => 'decimal:2',
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(UnitImage::class)->orderBy('sort_order');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(UnitInventory::class);
    }

    public function isStudio(): bool
    {
        return $this->bedrooms === 0;
    }

    public function maxOccupancy(): int
    {
        return $this->max_adults + $this->max_children;
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
