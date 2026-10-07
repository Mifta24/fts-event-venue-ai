<x-apartment-stage :apartment="$apartment" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :floor="$floor" :backdrop="$backdrop" :menu-items="$menuItems" :title="$labels['menu_facilities'].' · '.$apartment->name">
    <div class="stage-panels">
        <section class="lobby-content stage-panel-right @container" aria-label="{{ $labels['menu_facilities'] }}">
            <a href="{{ route('apartment.show', ['apartmentSlug' => $apartment->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_lobby'] }}" class="panel-close" aria-label="{{ $lobby['back'] }}"><span aria-hidden="true">×</span></a>
            <p class="lobby-eyebrow">{{ $lobby['floor'] }} {{ $floor }}</p>
            <h2>{{ $labels['menu_facilities'] }}</h2>
            @if ($sceneNarrations['facilities'])
                @include('apartment.narrator', ['text' => $sceneNarrations['facilities'], 'key' => 'facilities', 'inline' => true])
            @endif

            <div class="facility-grid">
                @forelse ($facilities as $item)
                    @php $title = $item->translatedTitle($locale); @endphp
                    <a href="{{ route('apartment.facility', ['apartmentSlug' => $apartment->slug, 'facilityId' => $item->id, 'lang' => $locale]) }}" data-stage-exit class="facility-card">
                        <span class="facility-card-photo">
                            @if ($item->image_url)
                                <img src="{{ $item->image_url }}" alt="" loading="lazy">
                            @endif
                            <span class="facility-card-icon"><x-facility-icon :name="$item->iconName()" /></span>
                        </span>
                        <span class="facility-card-body">
                            <span class="facility-card-index">{{ $floor }}.{{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="facility-card-title">{{ $title }}</span>
                            <span class="facility-card-text">{{ $item->translatedBody($locale) }}</span>
                        </span>
                        <span class="facility-card-go" aria-hidden="true">›</span>
                    </a>
                @empty
                    <p class="text-stone-500">{{ $lobby['empty'] }}</p>
                @endforelse
            </div>
        </section>
    </div>
</x-apartment-stage>
