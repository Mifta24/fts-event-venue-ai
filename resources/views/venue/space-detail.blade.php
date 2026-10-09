<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :foyer="$foyer" :narration="$narration" :scene="$scene" :cue="$cue" :backdrop="$backdrop" :menu-items="$menuItems" :title="$space->translatedName($locale).' · '.$venue->name">
    @if($spaces->isNotEmpty())
        <x-slot:sidebar>@include('venue.space-nav')</x-slot:sidebar>
    @endif

    <div class="stage-panels">
        @include('venue.space-scene')
    </div>
</x-venue-stage>
