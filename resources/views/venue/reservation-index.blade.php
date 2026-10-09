<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :foyer="$foyer" :narration="$narration" :scene="$scene" :cue="$cue" :backdrop="$backdrop" :menu-items="$menuItems" :title="$foyer['reservation'].' · '.$venue->name">
    <div class="stage-panels">
        @include('venue.reservation')
    </div>
</x-venue-stage>
