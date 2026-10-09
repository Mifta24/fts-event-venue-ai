{{-- The spaces directory board: every space with its type, size and daily rate, so the guest can step between spaces without going back. --}}
<aside class="space-directory" aria-label="{{ $labels['spaces_heading'] }}">
    <p class="space-directory-title"><span>{{ $labels['spaces_heading'] }}</span><span>{{ $spaces->count() }}</span></p>
    <nav class="space-nav">
        @foreach ($spaces as $item)
            <a href="{{ route('venue.space', ['venueSlug' => $venue->slug, 'spaceSlug' => $item->slug, 'lang' => $locale]) }}"
                data-stage-exit
                @if (($space ?? null)?->slug === $item->slug) aria-current="page" @endif>
                @if ($item->images->first())
                    <img class="space-nav-thumb" src="{{ $item->images->first()->image_source }}" alt="" loading="lazy">
                @endif
                <span class="space-nav-text">
                    <span class="space-nav-type">{{ $spaceTerms['type'][$item->space_type] ?? Str::headline($item->space_type) }}</span>
                    <span class="space-nav-name-row">
                        <span class="space-nav-name">{{ $item->translatedName($locale) }}</span>
                        <span class="space-nav-size">{{ number_format($item->maxGuests(), 0, ',', '.') }}&nbsp;{{ $foyer['guests'] }}</span>
                    </span>
                    <span class="space-nav-price">{{ $labels['from'] }} {{ $venue->currency }}&nbsp;{{ number_format((float) $item->base_price, 0, ',', '.') }} {{ $labels['per_day'] }}</span>
                </span>
                <span class="space-nav-go" aria-hidden="true">→</span>
            </a>
        @endforeach
    </nav>
</aside>
