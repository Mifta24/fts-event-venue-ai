@props(['venue', 'locale', 'supportedLocales', 'labels', 'foyer', 'narration', 'scene', 'cue', 'backdrop', 'menuItems', 'title' => null, 'welcome' => null, 'sidebar' => null])
{{-- The fixed full-screen stage every cue shares: photo, curtains, cue display, rundown panel and the planner chat dock. --}}
@php
    $foyerUrl = route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]);
    $currentKey = ['space' => 'spaces', 'facility' => 'facilities'][$scene] ?? $scene;
    $currentLabel = collect($menuItems)->firstWhere('key', $currentKey)['label'] ?? $foyer['home'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $venue->name.' · AI Event Planner' }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage-page antialiased" style="--planner-image: url('{{ $backdrop['avatarImage'] }}'); --stage-focus: {{ $backdrop['focus'] }}; --stage-focus-mobile: {{ $backdrop['focusMobile'] }}; --planner-zoom: {{ $backdrop['avatarZoom'] }}; --planner-focus: {{ $backdrop['avatarFocus'] }}">
    <main class="stage" data-foyer data-scene="{{ $scene }}" data-page="{{ $scene === 'foyer' ? 'home' : $scene }}" data-cue="{{ $cue }}" data-chat-dock="right">
        <div class="stage-loader" data-stage-loader role="status">
            <span class="curtain curtain-left" aria-hidden="true"></span>
            <span class="curtain curtain-right" aria-hidden="true"></span>
            <span class="stage-loader-cue" aria-hidden="true"><small>{{ $foyer['cue'] }}</small>{{ $cue }}</span>
            <p>{{ $foyer['loading'] }}</p>
        </div>
        <img src="{{ $backdrop['image'] }}" alt="" class="stage-image" fetchpriority="high">
        <div class="stage-shade"></div>
        @if ($scene !== 'space')
            <img src="{{ $backdrop['character'] }}" alt="{{ $foyer['assistant'] }}" class="stage-character" data-pose="{{ $backdrop['pose'] }}">
        @endif
        <p class="stage-tour" data-stage-tour aria-live="polite"></p>

        <header class="stage-header">
            <a href="{{ $foyerUrl }}" @if($scene !== 'foyer') data-stage-exit data-tour-line="{{ $narration['tour_foyer'] }}" @endif class="stage-brand">
                <x-venue-mark />
                <span class="min-w-0"><span class="stage-brand-name">{{ $venue->name }}</span><span class="stage-brand-city">{{ $venue->city }} · {{ $venue->country }}</span></span>
            </a>
            <div class="cue-display" aria-live="polite">
                <span class="cue-display-tag">{{ $foyer['cue'] }}</span>
                <span class="cue-display-code" data-cue-code>{{ $cue }}</span>
                <span class="cue-display-label">{{ $currentLabel }}</span>
            </div>
            <div class="stage-tools">
                <x-sound-toggle :on="$foyer['sound_on']" :off="$foyer['sound_off']" />
                <nav aria-label="Language" class="stage-lang">
                    @foreach ($supportedLocales as $code)
                        <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        {{-- The rundown: the foyer first, then every cue in order, like the running sheet backstage. --}}
        <nav class="rundown" aria-label="{{ $foyer['explore'] }}">
            <p class="rundown-title">{{ $foyer['explore'] }}</p>
            <ol class="rundown-list">
                <li style="--strip-order: 0">
                    <a href="{{ $foyerUrl }}" class="rundown-item" @if($scene !== 'foyer') data-stage-exit data-tour-line="{{ $narration['tour_foyer'] }}" @else aria-current="page" @endif>
                        <span class="rundown-key" aria-hidden="true">F</span>
                        <span class="rundown-label">{{ $foyer['home'] }}</span>
                    </a>
                </li>
                @foreach ($menuItems as $item)
                    <li style="--strip-order: {{ $loop->iteration }}">
                        <a href="{{ $item['href'] }}" class="rundown-item"
                            data-stage-exit @if($item['tour']) data-tour-line="{{ $item['tour'] }}" @endif data-topic="{{ $item['topic'] }}"
                            @if($item['key'] === $currentKey) aria-current="page" @endif>
                            <span class="rundown-key" aria-hidden="true">{{ $item['cue'] }}</span>
                            <span class="rundown-label">{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
            <span class="rundown-footer"><span class="status-dot" aria-hidden="true"></span>{{ $foyer['available'] }}</span>
        </nav>

        {{ $welcome }}

        {{ $sidebar }}

        {{ $slot }}

        <div data-chat-widget>
            @include('venue.chat')
        </div>
        <noscript><p class="stage-noscript">Aktifkan JavaScript untuk menggunakan navigasi dan AI Event Planner. {{ $venue->phone }}</p></noscript>
    </main>
</body>
</html>
