<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :foyer="$foyer" :narration="$narration" :scene="$scene" :cue="$cue" :backdrop="$backdrop" :menu-items="$menuItems" :title="$facility->translatedTitle($locale).' · '.$venue->name">
    @php
        $title = $facility->translatedTitle($locale);
        $facilityUrl = fn ($item) => route('venue.facility', ['venueSlug' => $venue->slug, 'facilityId' => $item->id, 'lang' => $locale]);
    @endphp

    <div class="stage-panels">
        <section class="foyer-content stage-panel-right @container" aria-label="{{ $title }}">
            <a href="{{ route('venue.facilities', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit class="panel-close" aria-label="{{ $foyer['back_facilities'] }}"><span aria-hidden="true">×</span></a>
            <article class="space-scene">
                <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
                    <p class="foyer-eyebrow">{{ $foyer['cue'] }} {{ $cue }} · {{ $foyer['facility_counter'] }} {{ $facilityIndex + 1 }} / {{ $facilities->count() }}</p>
                    @if ($facilities->count() > 1)
                        <nav class="flex gap-2" aria-label="{{ $labels['menu_facilities'] }}">
                            <a href="{{ $facilityUrl($previousFacility) }}" data-stage-exit class="space-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $foyer['prev_facility'] }}</a>
                            <a href="{{ $facilityUrl($nextFacility) }}" data-stage-exit class="space-scene-step" rel="next">{{ $foyer['next_facility'] }} <span aria-hidden="true">→</span></a>
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
                @include('venue.narrator', ['text' => $facility->translatedBody($locale), 'key' => 'facility-'.$facility->id, 'inline' => true])
                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="button" class="foyer-action" data-hero-quick-message="{{ str_replace(':name', $title, $foyer['ask_facility_q']) }}">{{ $foyer['ask_facility'] }} <span aria-hidden="true">↗</span></button>
                    <a href="{{ route('venue.facilities', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit class="space-scene-step">{{ $foyer['back_facilities'] }}</a>
                </div>
            </article>
        </section>
    </div>
</x-venue-stage>
