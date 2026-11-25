<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Department;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\BusinessDay;
use App\Support\DepartmentRevenue;
use App\Support\PeriodFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Pilotage » (dashboard.revenue, the Gérant): the establishment's turnover — the cash received
 * (client decision, 2026-11-25) — globally and per service (hébergement, restauration, plage et
 * piscine), over a period of business days, compared with the previous period and the same
 * period a year earlier, its evolution, and each sales service's activity.
 */
class PilotageController extends Controller
{
    public const PERIODS = [
        'aujourdhui' => "Aujourd'hui",
        'hier' => 'Hier',
        'semaine' => 'Cette semaine',
        'mois' => 'Ce mois',
        'mois-dernier' => 'Mois dernier',
        'annee' => 'Cette année',
    ];

    public function index(Request $request): View
    {
        $p = $this->periods($request);

        $current = DepartmentRevenue::totals($p['start'], $p['end']);
        $previous = DepartmentRevenue::totals($p['prevStart'], $p['prevEnd']);
        $lastYear = DepartmentRevenue::totals($p['lyStart'], $p['lyEnd']);
        $sum = fn (array $totals) => array_sum(array_column($totals, 'amount'));

        return view('admin.pilotage.index', [
            ...$p,
            'periods' => self::PERIODS,
            'departments' => DepartmentRevenue::visible($current, $previous, $lastYear),
            'current' => $current,
            'previous' => $previous,
            'lastYear' => $lastYear,
            'total' => $sum($current),
            'totalPrevious' => $sum($previous),
            'totalLastYear' => $sum($lastYear),
            'evolution' => DepartmentRevenue::evolution($p['from'], $p['to']),
            'activity' => DepartmentRevenue::salesActivity($p['start'], $p['end']),
        ]);
    }

    /** The evolution table as a spreadsheet (CSV, semicolons, UTF-8). */
    public function export(Request $request): StreamedResponse
    {
        $p = $this->periods($request);
        $evolution = DepartmentRevenue::evolution($p['from'], $p['to']);
        $departments = DepartmentRevenue::visible(DepartmentRevenue::totals($p['start'], $p['end']));
        Audit::record('export.pilotage', 'export', 'Export du chiffre d\'affaires par service du '.$p['from']->format('d/m/Y').' au '.$p['to']->format('d/m/Y'));

        return response()->streamDownload(function () use ($evolution, $departments) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$evolution['granularity'] === 'mois' ? 'Mois' : 'Journée', ...array_map(fn (Department $d) => $d->getLabel(), $departments), 'Total'], ';');
            foreach ($evolution['buckets'] as $bucket) {
                fputcsv($out, [$bucket['label'], ...array_map(fn (Department $d) => (int) round($bucket['amounts'][$d->value]), $departments), (int) round($bucket['total'])], ';');
            }
            fclose($out);
        }, 'chiffre-affaires-'.$p['from']->format('Y-m-d').'-au-'.$p['to']->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The chosen period (default: this month), the previous one of the same length and the same
     * dates a year earlier, as business-day bounds.
     *
     * @return array<string, mixed>
     */
    private function periods(Request $request): array
    {
        // Nothing chosen: this month (the Recettes page defaults to today).
        $chosen = collect(['periode', 'jour', 'du', 'au'])->contains(fn (string $key) => is_string($request->query($key)) && $request->query($key) !== '');
        if (! $chosen) {
            $request->query->set('periode', 'mois');
        }
        ['period' => $period, 'from' => $from, 'to' => $to] = PeriodFilter::fromRequest($request, self::PERIODS);

        $days = (int) $from->diffInDays($to) + 1;
        $prevTo = $from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);
        $lyFrom = $from->copy()->subYearNoOverflow();
        $lyTo = $to->copy()->subYearNoOverflow();

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'start' => BusinessDay::bounds($from)[0],
            'end' => BusinessDay::bounds($to)[1],
            'prevFrom' => $prevFrom,
            'prevTo' => $prevTo,
            'prevStart' => BusinessDay::bounds($prevFrom)[0],
            'prevEnd' => BusinessDay::bounds($prevTo)[1],
            'lyFrom' => $lyFrom,
            'lyTo' => $lyTo,
            'lyStart' => BusinessDay::bounds($lyFrom)[0],
            'lyEnd' => BusinessDay::bounds($lyTo)[1],
            'today' => BusinessDay::today(),
        ];
    }
}
