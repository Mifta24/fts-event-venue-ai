<x-apartment-stage :apartment="$apartment" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :floor="$floor" :backdrop="$backdrop" :menu-items="$menuItems" :title="$facility->translatedTitle($locale).' · '.$apartment->name">
    @php
        $title = $facility->translatedTitle($locale);
        $facilityUrl = fn ($item) => route('apartment.facility', ['apartmentSlug' => $apartment->slug, 'facilityId' => $item->id, 'lang' => $locale]);
    @endphp

    <div class="stage-panels">
        <section class="lobby-content stage-panel-right @container" aria-label="{{ $title }}">
            <a href="{{ route('apartment.facilities', ['apartmentSlug' => $apartment->slug, 'lang' => $locale]) }}" data-stage-exit class="panel-close" aria-label="{{ $lobby['back_facilities'] }}"><span aria-hidden="true">×</span></a>
            <article class="unit-scene">
                <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
                    <p class="lobby-eyebrow">{{ $lobby['floor'] }} {{ $floor }} · {{ $lobby['facility_counter'] }} {{ $facilityIndex + 1 }} / {{ $facilities->count() }}</p>
                    @if ($facilities->count() > 1)
                        <nav class="flex gap-2" aria-label="{{ $labels['menu_facilities'] }}">
                            <a href="{{ $facilityUrl($previousFacility) }}" data-stage-exit class="unit-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $lobby['prev_facility'] }}</a>
                            <a href="{{ $facilityUrl($nextFacility) }}" data-stage-exit class="unit-scene-step" rel="next">{{ $lobby['next_facility'] }} <span aria-hidden="true">→</span></a>
                        </nav>
                    @endif
                </div>
                @if ($facility->image_url)
                    <div class="facility-hero mt-5">
                        <img src="{{ $facility->image_url }}" alt="{{ $title }}">
                        <span class="facility-card-icon"><x-facility-icon :name="$facility->iconName()" /></span>
                    </div>
                @endif
                <h2>{{ $title }}</h2>
                @include('apartment.narrator', ['text' => $facility->translatedBody($locale), 'key' => 'facility-'.$facility->id, 'inline' => true])
                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="button" class="lobby-action" data-hero-quick-message="{{ str_replace(':name', $title, $lobby['ask_facility_q']) }}">{{ $lobby['ask_facility'] }} <span aria-hidden="true">↗</span></button>
                    <a href="{{ route('apartment.facilities', ['apartmentSlug' => $apartment->slug, 'lang' => $locale]) }}" data-stage-exit class="unit-scene-step">{{ $lobby['back_facilities'] }}</a>
                </div>
            </article>
        </section>
    </div>
</x-apartment-stage>
