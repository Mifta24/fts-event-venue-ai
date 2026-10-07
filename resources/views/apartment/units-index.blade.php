<x-apartment-stage :apartment="$apartment" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :floor="$floor" :backdrop="$backdrop" :menu-items="$menuItems" :title="$labels['units_heading'].' · '.$apartment->name">
    <x-slot:welcome>
        <div class="stage-welcome stage-welcome-scene">
            <span class="lobby-eyebrow">{{ $lobby['floor'] }} {{ $floor }}</span>
            <h1>{{ $labels['units_heading'] }}</h1>
            @if ($unitNarrations['units'])
                @include('apartment.narrator', ['text' => $unitNarrations['units'], 'key' => 'units', 'onStage' => true])
            @else
                <p>{{ $lobby['units_empty'] }}</p>
            @endif
        </div>
    </x-slot:welcome>

    @if ($unitTypes->isNotEmpty())
        <x-slot:sidebar>@include('apartment.unit-nav')</x-slot:sidebar>
    @endif
</x-apartment-stage>
