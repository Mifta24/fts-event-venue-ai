{{-- The brand mark: a proscenium arch with a spotlight beam, drawn rather than lettered so it reads at any size. --}}
<span {{ $attributes->merge(['class' => 'venue-mark']) }} aria-hidden="true">
    <svg viewBox="0 0 32 32" width="22" height="22" fill="none">
        <path d="M5 28V12a11 11 0 0 1 22 0v16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M9.5 28V13a6.5 6.5 0 0 1 13 0v15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" opacity=".55"/>
        <path d="M16 3.5v6" stroke="var(--signal, #e8c872)" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M12 28 16 14l4 14Z" fill="var(--signal, #e8c872)" opacity=".9"/>
        <path d="M3 28h26" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
    </svg>
</span>
