@props(['name' => 'star'])
<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @switch($name)
        @case('stage')
            <path d="M3 8c3 0 3-3 6-3M21 8c-3 0-3-3-6-3M3 8v3M21 8v3M3 11h18l1 6H2l1-6Z"/><path d="M6 20h12"/>
            @break
        @case('sound')
            <rect x="6" y="3" width="12" height="18" rx="2"/><circle cx="12" cy="15" r="3.2"/><circle cx="12" cy="7.5" r="1"/>
            @break
        @case('lighting')
            <path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2V16h5v-.1c0-.8.4-1.5 1-2A6 6 0 0 0 12 3Z"/>
            @break
        @case('catering')
            <path d="M7 3v8a2 2 0 0 0 2 2v8M5 3v5M9 3v5M16 21V3c2 1.5 3 4 3 7h-3"/>
            @break
        @case('parking')
            <path d="M5 17h14M5 17v-5l2-5h10l2 5v5M5 12h14"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/>
            @break
        @case('transport')
            <path d="M10.5 13.5 3 11l1-2 8 1 4-5a1.5 1.5 0 0 1 2 2l-5 4 1 8-2 1-2.5-7.5L6 15.5V18l-1.5 1-1-3-3-1 1-1.5h2.5Z"/>
            @break
        @case('place')
            <path d="M12 21s-6.5-5.4-6.5-11a6.5 6.5 0 0 1 13 0c0 5.6-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/>
            @break
        @case('wifi')
            <path d="M2.5 8.5a14 14 0 0 1 19 0M5.5 12a9.5 9.5 0 0 1 13 0M8.5 15.5a5 5 0 0 1 7 0"/><circle cx="12" cy="19" r="1" fill="currentColor"/>
            @break
        @case('bridal')
            <path d="M8 3h8l1.5 4-5.5 3-5.5-3L8 3Z"/><path d="M12 10v3M7.5 21l1.5-8h6l1.5 8"/>
            @break
        @case('transit')
            <rect x="5.5" y="3" width="13" height="14" rx="3"/><path d="M5.5 10h13M9 13.5h.01M15 13.5h.01M8 21l2-4M16 21l-2-4"/>
            @break
        @case('decor')
            <path d="M12 21v-8M12 13c-3 0-5-2-5-5 3 0 5 2 5 5ZM12 13c3 0 5-2 5-5-3 0-5 2-5 5Z"/><path d="M12 8c-2-1-2-3 0-5 2 2 2 4 0 5Z"/>
            @break
        @case('power')
            <path d="M13 2 4.5 13H11l-1 9 8.5-11H12l1-9Z"/>
            @break
        @case('security')
            <path d="M12 3.5 19 6v5.5c0 4.6-3 8.2-7 9.3-4-1.1-7-4.7-7-9.3V6l7-2.5Z"/><path d="M12 9v3.5M12 15.5h.01"/>
            @break
        @default
            <path d="m12 3 2.5 5.6 6 .6-4.5 4.1 1.3 6-5.3-3.1-5.3 3.1 1.3-6L3.5 9.2l6-.6L12 3Z"/>
    @endswitch
</svg>
