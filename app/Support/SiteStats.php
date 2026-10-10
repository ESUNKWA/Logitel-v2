<?php

namespace App\Support;

use App\Enums\StayRequestStatus;
use App\Models\BookingRequest;
use App\Models\SiteVisit;
use App\Models\StayRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Figures of « Site web → Statistiques », from App\Models\SiteVisit. A visit = a visitor's first
 * page of the day (`is_entry`); page views = every page. Calendar days (the website's audience
 * isn't tied to the hotel's business day).
 */
class SiteStats
{
    /** Period => [label, unit]. */
    public const PERIODS = [
        'jours' => ['30 derniers jours', 'day'],
        'mois' => ['12 derniers mois', 'month'],
        'annees' => ['Par année', 'year'],
    ];

    /**
     * Visits and page views between two days (inclusive).
     *
     * @return array{visits: int, views: int}
     */
    public static function totals(CarbonInterface $from, CarbonInterface $to): array
    {
        $row = SiteVisit::query()
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('count(*) as views, sum(case when is_entry then 1 else 0 end) as visits')
            ->first();

        return ['visits' => (int) ($row->visits ?? 0), 'views' => (int) ($row->views ?? 0)];
    }

    /**
     * The chart's buckets for a period, oldest first, the current one last.
     *
     * @return array{from: Carbon, to: Carbon, buckets: list<array{key: string, label: string, short: string, visits: int, views: int}>}
     */
    public static function series(string $period): array
    {
        $today = now()->startOfDay();
        $unit = self::PERIODS[$period][1] ?? 'day';

        [$from, $step, $keyFormat] = match ($unit) {
            'month' => [$today->copy()->startOfMonth()->subMonths(11), 'addMonth', 'Y-m'],
            'year' => [Carbon::parse((SiteVisit::query()->min('day') ?? $today->toDateString()))->startOfYear()->min($today->copy()->startOfYear()), 'addYear', 'Y'],
            default => [$today->copy()->subDays(29), 'addDay', 'Y-m-d'],
        };

        $buckets = [];
        for ($cursor = $from->copy(); $cursor->lessThanOrEqualTo($today); $cursor->{$step}()) {
            $buckets[$cursor->format($keyFormat)] = [
                'key' => $cursor->format($keyFormat),
                'label' => match ($unit) {
                    'month' => ucfirst($cursor->translatedFormat('F Y')),
                    'year' => $cursor->format('Y'),
                    default => ucfirst($cursor->translatedFormat('l j F')),
                },
                'short' => match ($unit) {
                    'month' => ucfirst($cursor->translatedFormat('M')),
                    'year' => $cursor->format('Y'),
                    default => $cursor->format('d/m'),
                },
                'visits' => 0,
                'views' => 0,
            ];
        }

        SiteVisit::query()
            ->whereBetween('day', [$from->toDateString(), $today->toDateString()])
            ->selectRaw('day, count(*) as views, sum(case when is_entry then 1 else 0 end) as visits')
            ->groupBy('day')
            ->get()
            ->each(function ($row) use (&$buckets, $keyFormat) {
                $key = Carbon::parse(substr((string) $row->getRawOriginal('day'), 0, 10))->format($keyFormat);
                if (isset($buckets[$key])) {
                    $buckets[$key]['visits'] += (int) $row->visits;
                    $buckets[$key]['views'] += (int) $row->views;
                }
            });

        return ['from' => $from, 'to' => $today, 'buckets' => array_values($buckets)];
    }

    /**
     * Visits per source or device (entries only), largest first, with shares.
     *
     * @return list<array{key: string, label: string, count: int, share: int}>
     */
    public static function breakdown(string $column, CarbonInterface $from, CarbonInterface $to): array
    {
        $labels = $column === 'device' ? SiteVisit::DEVICES : SiteVisit::SOURCES;
        $rows = SiteVisit::query()
            ->where('is_entry', true)
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("{$column} as k, count(*) as c")
            ->groupBy($column)
            ->orderByDesc('c')
            ->get();
        $total = max(1, $rows->sum('c'));

        return $rows->map(fn ($r) => [
            'key' => (string) $r->k,
            'label' => $labels[$r->k] ?? (string) $r->k,
            'count' => (int) $r->c,
            'share' => (int) round($r->c * 100 / $total),
        ])->values()->all();
    }

    /**
     * Most viewed pages.
     *
     * @return list<array{label: string, views: int, visitors: int}>
     */
    public static function topPages(CarbonInterface $from, CarbonInterface $to, int $limit = 10): array
    {
        return SiteVisit::query()
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('label, count(*) as views, count(distinct visitor) as visitors')
            ->groupBy('label')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'views' => (int) $r->views, 'visitors' => (int) $r->visitors])
            ->all();
    }

    /**
     * Other sites sending visitors (« Autres sites »), by host.
     *
     * @return list<array{host: string, count: int}>
     */
    public static function referrers(CarbonInterface $from, CarbonInterface $to, int $limit = 8): array
    {
        return SiteVisit::query()
            ->where('is_entry', true)
            ->whereNotNull('referrer_host')
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('referrer_host, count(*) as c')
            ->groupBy('referrer_host')
            ->orderByDesc('c')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['host' => (string) $r->referrer_host, 'count' => (int) $r->c])
            ->all();
    }

    /**
     * What visitors did: online booking requests (and how many became réservations), guest
     * requests from the lobby QR code (and how many were validated).
     *
     * @return array<string, int>
     */
    public static function conversions(CarbonInterface $from, CarbonInterface $to): array
    {
        $between = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $pageViews = fn (string $route) => SiteVisit::query()->where('route', $route)->whereBetween('day', [$from->toDateString(), $to->toDateString()])->count();

        return [
            'booking_page_views' => $pageViews('booking.create'),
            'booking_requests' => BookingRequest::query()->whereBetween('created_at', $between)->count(),
            'booking_handled' => BookingRequest::query()->whereBetween('created_at', $between)->where('status', BookingRequest::STATUS_HANDLED)->count(),
            'qr_page_views' => $pageViews('stay-request.index'),
            'stay_requests' => StayRequest::query()->whereBetween('created_at', $between)->count(),
            'stay_validated' => StayRequest::query()->whereBetween('created_at', $between)->where('status', StayRequestStatus::Validee)->count(),
        ];
    }
}
