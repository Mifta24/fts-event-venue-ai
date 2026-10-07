<x-apartment-stage :apartment="$apartment" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :floor="$floor" :backdrop="$backdrop" :menu-items="$menuItems" :title="$lobby['reservation'].' · '.$apartment->name">
    <div class="stage-panels">
        @include('apartment.reservation')
    </div>
</x-apartment-stage>
