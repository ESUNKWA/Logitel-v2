<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\SiteStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Site web → Statistiques »: the website's audience (visits per day / month / year, pages,
 * sources, devices) and what it led to (online booking requests, QR code requests).
 */
class SiteStatsController extends Controller
{
    public function index(Request $request): View
    {
        $period = array_key_exists($request->string('periode')->toString(), SiteStats::PERIODS) ? $request->string('periode')->toString() : 'jours';
        $series = SiteStats::series($period);
        [$from, $to] = [$series['from'], $series['to']];
        $today = now()->startOfDay();

        // Today, this month and this year, each against the same span of the period before.
        $compare = function ($start, $prevStart, $prevEnd) use ($today) {
            $current = SiteStats::totals($start, $today);
            $previous = SiteStats::totals($prevStart, $prevEnd);

            return [...$current, 'previous' => $previous['visits'], 'delta' => $previous['visits'] > 0 ? (int) round(($current['visits'] - $previous['visits']) * 100 / $previous['visits']) : null];
        };
        $elapsedInMonth = $today->day - 1;
        $prevMonthStart = $today->copy()->startOfMonth()->subMonthNoOverflow();

        return view('admin.site.stats', [
            'period' => $period,
            'series' => $series,
            'kpis' => [
                'today' => $compare($today, $today->copy()->subDay(), $today->copy()->subDay()),
                'month' => $compare($today->copy()->startOfMonth(), $prevMonthStart, $prevMonthStart->copy()->addDays($elapsedInMonth)->min($prevMonthStart->copy()->endOfMonth())),
                'year' => $compare($today->copy()->startOfYear(), $today->copy()->subYear()->startOfYear(), $today->copy()->subYear()),
            ],
            'totals' => SiteStats::totals($from, $to),
            'sources' => SiteStats::breakdown('source', $from, $to),
            'devices' => SiteStats::breakdown('device', $from, $to),
            'pages' => SiteStats::topPages($from, $to),
            'referrers' => SiteStats::referrers($from, $to),
            'conversions' => SiteStats::conversions($from, $to),
        ]);
    }
}
