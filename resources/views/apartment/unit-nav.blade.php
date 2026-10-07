{{-- The residences directory board: every unit type with its layout, floors and nightly rate, so the guest can step between units without going back. --}}
<aside class="unit-directory" aria-label="{{ $labels['units_heading'] }}">
    <p class="unit-directory-title"><span>{{ $labels['units_heading'] }}</span><span>{{ $unitTypes->count() }}</span></p>
    <nav class="unit-nav">
        @foreach ($unitTypes as $item)
            <a href="{{ route('apartment.unit', ['apartmentSlug' => $apartment->slug, 'unitSlug' => $item->slug, 'lang' => $locale]) }}"
                data-stage-exit
                @if (($unitType ?? null)?->slug === $item->slug) aria-current="page" @endif>
                @if ($item->images->first())
                    <img class="unit-nav-thumb" src="{{ $item->images->first()->image_source }}" alt="" loading="lazy">
                @endif
                <span class="unit-nav-text">
                    <span class="unit-nav-layout">{{ $item->isStudio() ? $lobby['studio'] : str_replace([':count', ':baths'], [$item->bedrooms, $item->bathrooms], $lobby['bedrooms']) }}@if($item->size_sqm) · {{ $item->size_sqm }} m²@endif</span>
                    <span class="unit-nav-name">{{ $item->translatedName($locale) }}</span>
                    <span class="unit-nav-price">{{ $labels['from'] }} {{ $apartment->currency }} {{ number_format((float) $item->base_price, 0, ',', '.') }} {{ $labels['per_night'] }}</span>
                </span>
                <span class="unit-nav-go" aria-hidden="true">→</span>
            </a>
        @endforeach
    </nav>
</aside>
