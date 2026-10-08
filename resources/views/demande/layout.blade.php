<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex">
        <meta name="theme-color" content="#15264a">
        @stack('head')
        <title>@yield('title', 'Choisir ma chambre') — {{ $hotelInfo->name() }}</title>
        <link rel="shortcut icon" href="{{ asset('assets/images/favicon.png') }}" type="image/png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gilda+Display&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
        <link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/plugins/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/demande.css') }}">
    </head>
    <body>
        <header class="dm-header">
            <a href="{{ route('stay-request.index') }}" class="dm-brand">{{ $hotelInfo->nameParts()[0] }} <span>{{ $hotelInfo->nameParts()[1] }}</span></a>
            <div class="dm-header__sub">@yield('subtitle', 'Choisissez votre chambre')</div>
        </header>

        <main class="dm-main">
            @if (session('notice'))
                <div class="alert alert-warning">{{ session('notice') }}</div>
            @endif

            @yield('content')
        </main>

        <footer class="dm-footer">
            Le règlement se fait à la réception : espèces, Orange Money ou Wave.
            @if ($hotelInfo->get('phone'))
                <br>Besoin d'aide ? Réception : <a href="{{ $hotelInfo->telHref($hotelInfo->get('phone')) }}">{{ $hotelInfo->get('phone') }}</a>
            @endif
        </footer>
        @stack('scripts')
    </body>
</html>
