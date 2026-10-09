@php
    $spaceName = $space->translatedName($locale);
    $money = fn ($value) => $venue->currency."\u{00A0}".number_format((float) $value, 0, ',', '.');
    $typeLabel = $spaceTerms['type'][$space->space_type] ?? Str::headline($space->space_type);
    $layouts = collect($space->layouts ?? [])->sortDesc();
    $primaryImage = $space->images->first();
    $spaceUrl = fn ($other) => route('venue.space', ['venueSlug' => $venue->slug, 'spaceSlug' => $other->slug, 'lang' => $locale]);
    $hasDiscount = $venue->weekday_discount_percent > 0 || $venue->multiday_discount_percent > 0;
@endphp
<section class="foyer-content @container" aria-label="{{ $spaceName }}">
    <a href="{{ route('venue.spaces', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit class="panel-close" aria-label="{{ $labels['spaces_heading'] }}"><span aria-hidden="true">×</span></a>
    <article class="space-scene">
        <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
            <p class="foyer-eyebrow">{{ $foyer['space_counter'] }} {{ str_pad((string) ($spaceIndex + 1), 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) $spaces->count(), 2, '0', STR_PAD_LEFT) }}</p>
            @if ($spaces->count() > 1)
                <nav class="flex gap-2" aria-label="{{ $foyer['space_scene'] }}">
                    <a href="{{ $spaceUrl($previousSpace) }}" data-stage-exit class="space-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $foyer['prev_space'] }}</a>
                    <a href="{{ $spaceUrl($nextSpace) }}" data-stage-exit class="space-scene-step" rel="next">{{ $foyer['next_space'] }} <span aria-hidden="true">→</span></a>
                </nav>
            @endif
        </div>

        <div class="mt-4 grid gap-7 @2xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div data-space-gallery>
                <div class="space-scene-photo">
                    @if ($primaryImage)
                        <img src="{{ $primaryImage->image_source }}" alt="{{ $primaryImage->alt_text }}" data-space-gallery-main>
                    @else
                        <div class="grid h-full place-items-center text-sm">{{ $foyer['no_photo'] }}</div>
                    @endif
                    <span class="space-scene-badge">{{ $typeLabel }}</span>
                </div>
                @if ($space->images->count() > 1)
                    <ul class="mt-2 grid grid-cols-4 gap-2" aria-label="{{ $foyer['gallery'] }}">
                        @foreach ($space->images as $imageIndex => $image)
                            <li>
                                <button type="button" class="space-scene-thumb" data-space-thumb data-src="{{ $image->image_source }}" data-alt="{{ $image->alt_text }}" aria-label="{{ $foyer['photo'] }} {{ $imageIndex + 1 }}" @if($imageIndex === 0) aria-current="true" @endif>
                                    <img src="{{ $image->image_source }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @include('venue.narrator', ['text' => $spaceNarrations['space'][$space->slug], 'key' => 'space-'.$space->slug])
            </div>

            <div class="min-w-0">
                <h2 class="mt-0!">{{ $spaceName }}</h2>
                <p class="space-scene-description">{{ $space->translatedDescription($locale) }}</p>

                <div class="space-scene-rate">
                    <p><span class="space-scene-rate-label">{{ $labels['from'] }}</span> <span class="space-scene-rate-amount"><strong>{{ $money($space->base_price) }}</strong> <span class="space-scene-rate-label">{{ $labels['per_day'] }}</span></span></p>
                    @if ($hasDiscount)
                        <p class="space-scene-discount">{{ str_replace([':weekday', ':multiday', ':days'], [$venue->weekday_discount_percent, $venue->multiday_discount_percent, \App\Models\Venue::MULTIDAY_MIN_DAYS], $foyer['discount_note']) }}</p>
                    @endif
                    <p class="space-scene-note">{{ $foyer['availability_note'] }}</p>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('venue.reservation', ['venueSlug' => $venue->slug, 'lang' => $locale, 'space' => $space->slug]) }}" data-stage-exit data-tour-line="{{ $narration['tour_reservation'] }}" class="foyer-action">{{ $foyer['reserve_space'] }} <span aria-hidden="true">↗</span></a>
                    <button type="button" class="space-scene-step" data-hero-quick-message="{{ str_replace(':name', $spaceName, $foyer['ask_space_q']) }}">{{ $foyer['ask_space'] }}</button>
                </div>

                <dl class="space-scene-facts">
                    <div><dt>{{ $foyer['capacity'] }}</dt><dd>{{ number_format($space->maxGuests(), 0, ',', '.') }} {{ $foyer['guests'] }}</dd></div>
                    @if ($space->size_sqm)
                        <div><dt>{{ $foyer['size'] }}</dt><dd>{{ $space->size_sqm }} m²</dd></div>
                    @endif
                    @if ($space->ceiling_height_m)
                        <div><dt>{{ $foyer['ceiling'] }}</dt><dd>{{ rtrim(rtrim((string) $space->ceiling_height_m, '0'), '.') }} m</dd></div>
                    @endif
                    @if ($space->level_label)
                        <div><dt>{{ $foyer['level'] }}</dt><dd>{{ $space->level_label }}</dd></div>
                    @endif
                    <div><dt>{{ $foyer['setting'] }}</dt><dd>{{ $typeLabel }}</dd></div>
                    <div><dt>{{ $foyer['av'] }}</dt><dd>{{ $space->av_included ? $foyer['av_yes'] : $foyer['av_no'] }}</dd></div>
                    <div>
                        <dt>{{ $foyer['catering'] }}</dt>
                        <dd>
                            @if ($space->catering_available)
                                {{ $foyer['catering_yes'] }}@if ($space->catering_price > 0) (+{{ $money($space->catering_price) }})@endif
                            @else
                                {{ $foyer['catering_no'] }}
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($layouts->isNotEmpty())
                    <h3 class="space-scene-subheading">{{ $foyer['layouts'] }}</h3>
                    <ul class="space-scene-layouts">
                        @php $topCapacity = max(1, $layouts->first()); @endphp
                        @foreach ($layouts as $layout => $capacity)
                            <li style="--fill: {{ round($capacity / $topCapacity * 100) }}%">
                                <span>{{ $spaceTerms['layout'][$layout] ?? Str::headline($layout) }}</span>
                                <strong>{{ number_format($capacity, 0, ',', '.') }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (! empty($space->amenities))
                    <h3 class="space-scene-subheading">{{ $foyer['amenities'] }}</h3>
                    <ul class="space-scene-amenities">
                        @foreach ($space->amenities as $amenity)
                            <li>{{ $spaceTerms['amenity'][$amenity] ?? Str::headline($amenity) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </article>
</section>
