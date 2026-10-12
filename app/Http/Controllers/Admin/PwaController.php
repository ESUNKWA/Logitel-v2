<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\HotelInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The installable application (PWA): manifest, service worker and offline page — all public (the
 * login page needs them) and scoped to the application's own path (/admin/ today, the root of
 * its host once ADMIN_DOMAIN is set), so the website is never part of the installed app.
 */
class PwaController extends Controller
{
    /** Bumped whenever the service worker or the files it pre-caches change. */
    public const VERSION = '2026-10-12.2';

    /**
     * The application's root path with a trailing slash: « /admin/ » or « / ».
     */
    public static function scope(): string
    {
        return rtrim((string) parse_url(route('admin.dashboard'), PHP_URL_PATH), '/').'/';
    }

    public function manifest(HotelInfo $hotel): JsonResponse
    {
        $scope = self::scope();
        $icon = fn (string $file, string $size, string $purpose = 'any') => [
            'src' => asset('app/images/pwa/'.$file), 'sizes' => $size, 'type' => 'image/png', 'purpose' => $purpose,
        ];

        return response()->json([
            'id' => $scope,
            'name' => $hotel->name().' — Gestion',
            'short_name' => $hotel->name(),
            'description' => 'Gestion hôtelière : chambres, affectations, réservations et encaissements.',
            'lang' => 'fr',
            'dir' => 'ltr',
            'start_url' => $scope.'?source=app',
            'scope' => $scope,
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#15264a',
            'theme_color' => '#15264a',
            'categories' => ['business', 'productivity'],
            'icons' => [
                $icon('icon-192.png', '192x192'),
                $icon('icon-512.png', '512x512'),
                $icon('maskable-512.png', '512x512', 'maskable'),
            ],
            'shortcuts' => [
                ['name' => 'Chambres', 'url' => route('admin.rooms.board', [], false), 'icons' => [$icon('icon-192.png', '192x192')]],
                ['name' => 'Affectations', 'url' => route('admin.affectations.index', [], false), 'icons' => [$icon('icon-192.png', '192x192')]],
                ['name' => 'Réservations', 'url' => route('admin.reservations.index', [], false), 'icons' => [$icon('icon-192.png', '192x192')]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Served from the application's path so its default scope is the application only.
     */
    public function serviceWorker(): Response
    {
        $script = view('admin.pwa.service-worker', [
            'version' => self::VERSION,
            'scope' => self::scope(),
            'offlineUrl' => route('admin.pwa.offline', [], false),
            'precache' => [
                route('admin.pwa.offline', [], false),
                '/app/css/admin.css',
                '/app/js/admin.js',
                '/app/js/pwa.js',
                '/vendor/bootstrap/bootstrap.min.css',
                '/vendor/fontawesome/css/all.min.css',
                '/app/images/pwa/icon-192.png',
            ],
        ])->render();

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => self::scope(),
        ]);
    }

    public function offline(): View
    {
        return view('admin.pwa.offline');
    }

    /**
     * Digital Asset Links for the Android APK (Trusted Web Activity): 404 until configured.
     */
    public function assetLinks(): JsonResponse
    {
        $package = config('pwa.android_package');
        $fingerprints = config('pwa.android_fingerprints');
        abort_if(! $package || $fingerprints === [], 404);

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => $package, 'sha256_cert_fingerprints' => $fingerprints],
        ]], 200, [], JSON_UNESCAPED_SLASHES);
    }
}
