{{-- Installable application (PWA): only on the application's pages and its login page — never
     on the website. admin.js registers the service worker read from meta[name="app-sw"]. --}}
<link rel="manifest" href="{{ route('admin.pwa.manifest') }}">
<meta name="theme-color" content="#15264a">
<meta name="app-sw" content="{{ route('admin.pwa.service-worker') }}" data-scope="{{ \App\Http\Controllers\Admin\PwaController::scope() }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $hotelInfo->name() }}">
<link rel="apple-touch-icon" href="{{ asset('app/images/pwa/apple-touch-icon.png') }}">
