<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A page viewed on the public website (App\Http\Middleware\RecordSiteVisit). No cookie and no
 * IP stored: the visitor is a hash of the day, IP, browser and the app key, so the same person
 * is one visitor for the day and can't be followed from one day to the next. A « visit » is a
 * visitor's first page of the day (`is_entry`), which also carries where they came from.
 */
class SiteVisit extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['day' => 'date', 'visited_at' => 'datetime', 'is_entry' => 'boolean'];
    }

    /** Where visitors come from: key => label. */
    public const SOURCES = [
        'direct' => 'Accès direct',
        'google' => 'Google',
        'search' => 'Autres moteurs de recherche',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp',
        'tiktok' => 'TikTok',
        'x' => 'X (Twitter)',
        'other' => 'Autres sites',
    ];

    public const DEVICES = ['mobile' => 'Téléphone', 'tablet' => 'Tablette', 'desktop' => 'Ordinateur'];

    /** Page names per route. */
    public const PAGES = [
        'home' => 'Accueil',
        'rooms.index' => 'Chambres',
        'services.index' => 'Services',
        'events.index' => 'Événements',
        'gallery' => 'Galerie',
        'contact' => 'Contact',
        'booking.create' => 'Réserver (formulaire)',
        'booking.show' => 'Réserver (demande envoyée)',
        'stay-request.index' => 'Demande sur place (QR code)',
        'stay-request.create' => 'Demande sur place : formulaire',
        'stay-request.show' => 'Demande sur place : suivi',
    ];

    /**
     * Crawlers, link previews (WhatsApp/Facebook fetch the page to build a preview), monitoring
     * tools and scripts: not people.
     */
    public static function isBot(?string $userAgent): bool
    {
        return $userAgent === null || trim($userAgent) === ''
            || (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|facebookcatalog|whatsapp|telegram|preview|curl|wget|python|java\/|go-http|okhttp|axios|node-fetch|headless|lighthouse|pingdom|uptime|monitor|scan|httpclient|libwww|php\//i', $userAgent);
    }

    public static function deviceOf(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        return match (true) {
            (bool) preg_match('/ipad|tablet|kindle|silk|(android(?!.*mobile))/i', $ua) => 'tablet',
            (bool) preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua) => 'mobile',
            default => 'desktop',
        };
    }

    /**
     * [source, referrer host] from the Referer header; a link from the site itself is « internal ».
     *
     * @return array{0: string, 1: string|null}
     */
    public static function sourceOf(?string $referrer, string $ownHost): array
    {
        $host = Str::lower((string) parse_url((string) $referrer, PHP_URL_HOST));
        $host = preg_replace('/^(www|m|l|lm|mobile)\./', '', $host);

        if ($host === '') {
            return ['direct', null];
        }
        if ($host === preg_replace('/^www\./', '', Str::lower($ownHost))) {
            return ['internal', null];
        }

        $source = match (true) {
            (bool) preg_match('/(^|\.)google\./', $host) => 'google',
            (bool) preg_match('/bing\.|yahoo\.|duckduckgo\.|qwant\.|ecosia\.|yandex\.|baidu\./', $host) => 'search',
            (bool) preg_match('/facebook\.|fb\.(me|com)|messenger\./', $host) => 'facebook',
            str_contains($host, 'instagram.') => 'instagram',
            (bool) preg_match('/whatsapp\.|wa\.me/', $host) => 'whatsapp',
            str_contains($host, 'tiktok.') => 'tiktok',
            (bool) preg_match('/(^|\.)(t\.co|twitter\.com|x\.com)$/', $host) => 'x',
            default => 'other',
        };

        return [$source, Str::limit($host, 120, '')];
    }

    /**
     * Anonymous visitor id for today (changes every day, can't be reversed to the IP).
     */
    public static function visitorHash(Request $request): string
    {
        return hash('sha256', now()->toDateString().'|'.$request->ip().'|'.$request->userAgent().'|'.config('app.key'));
    }

    public static function sourceLabel(string $source): string
    {
        return self::SOURCES[$source] ?? $source;
    }
}
