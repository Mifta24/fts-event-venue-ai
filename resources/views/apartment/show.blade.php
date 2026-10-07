@php
    $money = fn ($value) => $apartment->currency.' '.number_format((float) $value, 0, ',', '.');
@endphp
<x-apartment-stage :apartment="$apartment" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :floor="$floor" :backdrop="$backdrop" :menu-items="$menuItems">
    <x-slot:welcome>
        <div class="stage-welcome">
            <span class="lobby-eyebrow">{{ $apartment->name }} · {{ $apartment->city }}</span>
            <h1>{{ $lobby['welcome'] }} <em>{{ $lobby['lobby'] }}.</em></h1>
            <p>{{ $lobby['intro'] }}</p>

            <dl class="lobby-stats">
                @if ($unitTypes->isNotEmpty())
                    <div><dt>{{ $lobby['stat_types'] }}</dt><dd>{{ str_pad((string) $unitTypes->count(), 2, '0', STR_PAD_LEFT) }}</dd></div>
                    <div><dt>{{ $lobby['stat_from'] }}</dt><dd>{{ $money($unitTypes->min('base_price')) }}</dd></div>
                @endif
                @if ($apartment->monthly_discount_percent > 0)
                    <div><dt>{{ $lobby['stat_monthly'] }}</dt><dd>{{ str_replace(':percent', (string) $apartment->monthly_discount_percent, $lobby['stat_monthly_value']) }}</dd></div>
                @elseif ($apartment->check_in_time)
                    <div><dt>{{ $lobby['stat_checkin'] }}</dt><dd>{{ substr($apartment->check_in_time, 0, 5) }}</dd></div>
                @endif
            </dl>
        </div>
    </x-slot:welcome>
</x-apartment-stage>
