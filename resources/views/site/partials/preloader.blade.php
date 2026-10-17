{{-- Page loader: the hotel's logo (Configuration → Hôtel), else its name as the wordmark.
     Keeps the template's .preloader class, faded out by theme.js once the page has loaded. --}}
<div class="preloader" role="status" aria-label="Chargement…">
    <div class="site-preloader">
        @if ($hotelInfo->logoUrl())
            <img src="{{ $hotelInfo->logoUrl() }}" alt="{{ $hotelInfo->name() }}" class="site-preloader__logo">
        @else
            <x-site::brand-logo variant="dark" />
        @endif
        <span class="site-preloader__bar" aria-hidden="true"></span>
    </div>
</div>
<noscript><style>.preloader { display: none !important; }</style></noscript>
