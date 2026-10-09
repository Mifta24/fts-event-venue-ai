@php
    $money = fn ($value) => $venue->currency.' '.number_format((float) $value, 0, ',', '.');
    $spacesItem = collect($menuItems)->firstWhere('key', 'spaces');
    $reservationItem = collect($menuItems)->firstWhere('key', 'reservation');
    $largestCapacity = $spaces->max(fn ($space) => $space->maxGuests());
@endphp
<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :foyer="$foyer" :narration="$narration" :scene="$scene" :cue="$cue" :backdrop="$backdrop" :menu-items="$menuItems">
    <x-slot:welcome>
        <div class="stage-welcome">
            <span class="foyer-eyebrow">{{ $venue->name }} · {{ $venue->city }}</span>
            <h1>{{ $foyer['welcome'] }} <em>{{ $foyer['welcome_em'] }}.</em></h1>
            <p>{{ $foyer['intro'] }}</p>

            @if ($spaces->isNotEmpty())
                <div class="foyer-cta">
                    <a href="{{ $spacesItem['href'] }}" class="foyer-action foyer-action-signal" data-stage-exit data-tour-line="{{ $spacesItem['tour'] }}" data-topic="{{ $spacesItem['topic'] }}">{{ $foyer['cta_spaces'] }} <span aria-hidden="true">→</span></a>
                    <a href="{{ $reservationItem['href'] }}" class="foyer-action-ghost" data-stage-exit data-tour-line="{{ $reservationItem['tour'] }}" data-topic="{{ $reservationItem['topic'] }}">{{ $foyer['start_booking'] }}</a>
                </div>
            @endif

            <dl class="foyer-stats">
                @if ($spaces->isNotEmpty())
                    <div><dt>{{ $foyer['stat_from'] }}</dt><dd>{{ $money($spaces->min('base_price')) }}</dd></div>
                    <div><dt>{{ $foyer['stat_capacity'] }}</dt><dd>{{ str_replace(':count', number_format($largestCapacity, 0, ',', '.'), $foyer['stat_capacity_value']) }}</dd></div>
                @endif
                @if ($venue->weekday_discount_percent > 0)
                    <div><dt>{{ $foyer['stat_discount'] }}</dt><dd>{{ str_replace(':percent', (string) $venue->weekday_discount_percent, $foyer['stat_discount_value']) }}</dd></div>
                @elseif ($venue->load_in_time)
                    <div><dt>{{ $foyer['stat_load_in'] }}</dt><dd>{{ substr($venue->load_in_time, 0, 5) }}</dd></div>
                @endif
            </dl>
        </div>
    </x-slot:welcome>
</x-venue-stage>
