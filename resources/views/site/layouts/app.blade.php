<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="x-ua-compatible" content="ie=edge">
        <meta name="description" content="@yield('meta_description', "Hôtel Evasion - Hôtel & Resort")">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>@yield('title', 'Hôtel Evasion - Hôtel & Resort')</title>
        <link rel="shortcut icon" href="{{ asset('site/images/favicon.png') }}" type="image/png">
        <link href="https://fonts.googleapis.com/css2?family=Gilda+Display&family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('site/fonts/fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('site/css/plugins/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('site/css/plugins/slick.css') }}">
        <link rel="stylesheet" href="{{ asset('site/css/plugins/magnific-popup.css') }}">
        <link rel="stylesheet" href="{{ asset('site/css/plugins/nice-select.css') }}">
        <link rel="stylesheet" href="{{ asset('site/css/plugins/aos.css') }}">
        <link rel="stylesheet" href="{{ asset('site/css/spacings.css') }}">
        <link rel="stylesheet" href="{{ asset('site/css/style.css') }}">
        <link rel="stylesheet" href="{{ asset('site/css/brand.css') }}">
        <link rel="stylesheet" href="{{ asset('site/css/site.css') }}">
    </head>
    <body>
        @include('site.partials.preloader')
        <div class="offcanvas__overlay"></div>

        @include('site.partials.header')

        <main>
            @yield('content')
        </main>

        @include('site.partials.footer')

        <div class="back-to-top"><i class="far fa-angle-up"></i></div>

        <script src="{{ asset('site/js/plugins/jquery-3.7.1.min.js') }}"></script>
        <script src="{{ asset('site/js/plugins/popper.min.js') }}"></script>
        <script src="{{ asset('site/js/plugins/bootstrap.min.js') }}"></script>
        <script src="{{ asset('site/js/plugins/slick.min.js') }}"></script>
        <script src="{{ asset('site/js/plugins/jquery.magnific-popup.min.js') }}"></script>
        <script src="{{ asset('site/js/plugins/jquery.waypoints.js') }}"></script>
        <script src="{{ asset('site/js/plugins/jquery.counterup.min.js') }}"></script>
        <script src="{{ asset('site/js/plugins/jquery.nice-select.min.js') }}"></script>
        <script src="{{ asset('site/js/plugins/imagesloaded.min.js') }}"></script>
        <script src="{{ asset('site/js/plugins/isotope.min.js') }}"></script>
        <script src="{{ asset('site/js/plugins/aos.js') }}"></script>
        <script src="{{ asset('site/js/theme.js') }}"></script>
        @stack('scripts')
    </body>
</html>
