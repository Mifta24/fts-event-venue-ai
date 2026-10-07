<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FTS Apartment AI</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage-page antialiased">
    <main class="stage stage-opening" data-floor="L">
        <div class="stage-loader" data-stage-loader role="status">
            <span class="lift-door lift-door-left" aria-hidden="true"></span>
            <span class="lift-door lift-door-right" aria-hidden="true"></span>
            <span class="stage-loader-floor" aria-hidden="true">L</span>
            <p>{{ $opening['loading'] }}</p>
        </div>
        <img src="{{ $openingImage }}" alt="" class="stage-image" fetchpriority="high">
        <div class="stage-shade"></div>

        <header class="stage-header">
            <span class="stage-brand">
                <x-apartment-mark />
                <span class="stage-brand-name">FTS Apartment AI</span>
            </span>
            <div class="stage-tools">
                <x-sound-toggle :on="$opening['sound_on']" :off="$opening['sound_off']" />
                <nav aria-label="Language" class="stage-lang">
                    @foreach ($supportedLocales as $code)
                        <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        <section class="opening-panel" aria-labelledby="opening-title">
            <p class="lobby-eyebrow">{{ $opening['eyebrow'] }}</p>
            <h1 id="opening-title">{{ $opening['welcome'] }} <em>{{ $opening['welcome_em'] }}</em></h1>
            <p class="opening-tagline">{{ $opening['tagline'] }}</p>

            <div class="opening-enter">
                @forelse ($apartments as $apartment)
                    <a href="{{ route('apartment.show', ['apartmentSlug' => $apartment->slug, 'lang' => $locale]) }}" class="opening-button" data-stage-exit>
                        <span class="lift-key" aria-hidden="true">L</span>
                        <span>{{ str_replace(':name', $apartment->name, $opening['enter']) }}</span>
                        <span aria-hidden="true">→</span>
                    </a>
                @empty
                    <p class="opening-empty">{{ $opening['empty'] }}</p>
                @endforelse
            </div>
        </section>

        @if ($apartments->count() === 1)
            @php
                $apartmentSlug = $apartments->first()->slug;
                $openingLinks = [
                    'staff' => route('apartment.staff', ['apartmentSlug' => $apartmentSlug, 'lang' => $locale]),
                    'reservation' => route('apartment.reservation', ['apartmentSlug' => $apartmentSlug, 'lang' => $locale]),
                    'facilities' => route('apartment.facilities', ['apartmentSlug' => $apartmentSlug, 'lang' => $locale]),
                    'units' => route('apartment.units', ['apartmentSlug' => $apartmentSlug, 'lang' => $locale]),
                ];
            @endphp
            {{-- A building directory board, top floor first, as it hangs beside the lifts. --}}
            <aside class="opening-directory" aria-label="{{ $opening['directory'] }}">
                <p class="opening-directory-title">{{ $opening['directory'] }}</p>
                <ul class="opening-links">
                    @foreach ($openingLinks as $key => $href)
                        <li><a data-stage-exit href="{{ $href }}"><span class="opening-links-floor">{{ $floors[$key] }}</span><span>{{ $opening[$key] }}</span><span aria-hidden="true">→</span></a></li>
                    @endforeach
                </ul>
            </aside>
        @endif
    </main>
</body>
</html>
