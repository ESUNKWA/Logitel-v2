<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="x-ua-compatible" content="ie=edge">
        <meta name="description" content="@yield('meta_description', "Hôtel Evation - Hôtel & Resort")">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>@yield('title', 'Hôtel Evation - Hôtel & Resort')</title>
        <link rel="shortcut icon" href="{{ asset('assets/images/favicon.png') }}" type="image/png">
        <link href="https://fonts.googleapis.com/css2?family=Gilda+Display&family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/plugins/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/plugins/slick.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/plugins/magnific-popup.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/plugins/nice-select.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/plugins/aos.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/spacings.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/brand.css') }}">
    </head>
    <body>
        <div class="preloader">
            <div class="loading-wrapper">
                <div class="loading"></div>
                <div id="loading-icon"><img src="{{ asset('assets/images/loader.png') }}" alt="loader"></div>
            </div>
        </div>
        <div class="offcanvas__overlay"></div>

        @include('partials.hotel-header')

        <main>
            @yield('content')
        </main>

        @include('partials.hotel-footer')

        <div class="back-to-top"><i class="far fa-angle-up"></i></div>

        <script src="{{ asset('assets/js/plugins/jquery-3.7.1.min.js') }}"></script>
        <script src="{{ asset('assets/js/plugins/popper.min.js') }}"></script>
        <script src="{{ asset('assets/js/plugins/bootstrap.min.js') }}"></script>
        <script src="{{ asset('assets/js/plugins/slick.min.js') }}"></script>
        <script src="{{ asset('assets/js/plugins/jquery.magnific-popup.min.js') }}"></script>
        <script src="{{ asset('assets/js/plugins/jquery.waypoints.js') }}"></script>
        <script src="{{ asset('assets/js/plugins/jquery.counterup.min.js') }}"></script>
        <script src="{{ asset('assets/js/plugins/jquery.nice-select.min.js') }}"></script>
        <script src="{{ asset('assets/js/plugins/imagesloaded.min.js') }}"></script>
        <script src="{{ asset('assets/js/plugins/isotope.min.js') }}"></script>
        <script src="{{ asset('assets/js/plugins/aos.js') }}"></script>
        <script src="{{ asset('assets/js/theme.js') }}"></script>
    </body>
</html>
