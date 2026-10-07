<x-apartment-stage :apartment="$apartment" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :floor="$floor" :backdrop="$backdrop" :menu-items="$menuItems" :title="$unitType->translatedName($locale).' · '.$apartment->name">
    @if($unitTypes->isNotEmpty())
        <x-slot:sidebar>@include('apartment.unit-nav')</x-slot:sidebar>
    @endif

    <div class="stage-panels">
        @include('apartment.unit-scene')
    </div>
</x-apartment-stage>
