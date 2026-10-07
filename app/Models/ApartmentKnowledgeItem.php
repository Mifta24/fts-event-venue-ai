<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The approved knowledge base the AI Concierge retrieves from. This is the
 * ONLY place apartment facts may come from — the model is never allowed to
 * answer an apartment-knowledge question from its own memory.
 */
#[Fillable([
    'apartment_id',
    'category',
    'title',
    'body',
    'translations',
    'tags',
    'image_url',
    'is_active',
    'sort_order',
])]
class ApartmentKnowledgeItem extends Model
{
    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_FACILITIES = 'facilities';

    public const CATEGORY_POLICIES = 'policies';

    public const CATEGORY_DINING = 'dining';

    public const CATEGORY_TRANSPORT = 'transport';

    public const CATEGORY_FAQ = 'faq';

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    /**
     * A short icon name for this entry, taken from its tags or title so the
     * facilities cards can show a matching symbol without another column.
     */
    public function iconName(): string
    {
        $haystack = Str::lower($this->title.' '.implode(' ', $this->tags ?? []));

        foreach ([
            'pool' => ['pool', 'swim', 'kolam'],
            'gym' => ['gym', 'fitness', 'kebugaran'],
            'laundry' => ['laundry', 'binatu', 'cuci'],
            'work' => ['co-working', 'coworking', 'workspace', 'kerja'],
            'parking' => ['parking', 'parkir', 'car park'],
            'transit' => ['mrt', 'train', 'kereta', 'station', 'stasiun', 'transit'],
            'transport' => ['airport', 'bandara', 'transfer', 'shuttle', 'antar-jemput'],
            'housekeeping' => ['housekeeping', 'cleaning', 'kebersihan'],
            'security' => ['security', 'keamanan', 'access card', 'kartu akses'],
            'shop' => ['minimarket', 'grocery', 'mall', 'belanja'],
            'dining' => ['breakfast', 'sarapan', 'restaurant', 'restoran', 'dining', 'café', 'cafe', 'kafe', 'makan'],
            'place' => ['nearby', 'terdekat', 'neighbourhood', 'lingkungan', 'attraction'],
            'wifi' => ['wifi', 'wi-fi', 'internet'],
        ] as $icon => $needles) {
            foreach ($needles as $needle) {
                if (Str::contains($haystack, $needle)) {
                    return $icon;
                }
            }
        }

        return 'star';
    }

    public function translatedTitle(string $locale): string
    {
        $value = $this->translations[$locale]['title'] ?? null;

        return filled($value) ? $value : $this->title;
    }

    public function translatedBody(string $locale): string
    {
        $value = $this->translations[$locale]['body'] ?? null;

        return filled($value) ? $value : $this->body;
    }
}
