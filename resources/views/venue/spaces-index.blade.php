<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :foyer="$foyer" :narration="$narration" :scene="$scene" :cue="$cue" :backdrop="$backdrop" :menu-items="$menuItems" :title="$labels['spaces_heading'].' · '.$venue->name">
    <x-slot:welcome>
        <div class="stage-welcome stage-welcome-scene">
            <span class="foyer-eyebrow">{{ $foyer['cue'] }} {{ $cue }}</span>
            <h1>{{ $labels['spaces_heading'] }}</h1>
            @if ($spaceNarrations['spaces'])
                @include('venue.narrator', ['text' => $spaceNarrations['spaces'], 'key' => 'spaces', 'onStage' => true])
            @else
                <p>{{ $foyer['spaces_empty'] }}</p>
            @endif
        </div>
    </x-slot:welcome>

    @if ($spaces->isNotEmpty())
        <x-slot:sidebar>@include('venue.space-nav')</x-slot:sidebar>
    @endif
</x-venue-stage>
