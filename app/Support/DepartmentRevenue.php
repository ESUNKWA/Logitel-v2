<?php

namespace App\Support;

use App\Enums\Department;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Turnover per service (client decision, 2026-11-25: the cash received — « CA encaissé » —, by
 * `payments.paid_at`, in business days), for « Pilotage »: totals, comparisons and evolution.
 * Every figure reads `payments.department`, stamped from the invoice when the payment was made.
 */
final class DepartmentRevenue
{
    /**
     * Cash received per service over [start, end): amount and number of encaissements (a mixed
     * payment counts once).
     *
     * @return array<string, array{amount: float, count: int}> keyed by Department value
     */
    public static function totals(CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = Payment::query()->where('paid_at', '>=', $start)->where('paid_at', '<', $end)
            ->selectRaw('department, sum(amount) as amount, count(distinct coalesce(cast(settlement as varchar(40)), cast(id as varchar(40)))) as n')
            ->groupBy('department')->toBase()->get()->keyBy('department');

        $totals = [];
        foreach (Department::cases() as $department) {
            $row = $rows->get($department->value);
            $totals[$department->value] = ['amount' => (float) ($row->amount ?? 0), 'count' => (int) ($row->n ?? 0)];
        }

        return $totals;
    }

    /**
     * Cash received per bucket (business day, or month for ranges over 62 days) and service.
     *
     * @return array{granularity: string, buckets: list<array{key: string, label: string, amounts: array<string, float>, total: float}>}
     */
    public static function evolution(CarbonInterface $from, CarbonInterface $to): array
    {
        $monthly = $from->diffInDays($to) > 62;
        $buckets = [];
        for ($d = Carbon::parse($from->toDateString()); $d->lessThanOrEqualTo($to); $monthly ? $d->addMonthNoOverflow()->startOfMonth() : $d->addDay()) {
            $key = $monthly ? $d->format('Y-m') : $d->toDateString();
            $buckets[$key] ??= ['key' => $key, 'label' => $monthly ? self::monthLabel($d) : $d->format('d/m'),
                'amounts' => array_fill_keys(array_map(fn (Department $s) => $s->value, Department::cases()), 0.0), 'total' => 0.0];
        }

        [$start] = BusinessDay::bounds($from);
        [, $end] = BusinessDay::bounds($to);
        Payment::query()->where('paid_at', '>=', $start)->where('paid_at', '<', $end)
            ->select(['id', 'paid_at', 'amount', 'department'])->orderBy('id')
            ->chunk(1000, function ($payments) use (&$buckets, $monthly) {
                foreach ($payments as $payment) {
                    $day = BusinessDay::dateOf($payment->paid_at);
                    $key = $monthly ? $day->format('Y-m') : $day->toDateString();
                    if (! isset($buckets[$key])) {
                        continue;
                    }
                    $service = $payment->department?->value ?? Department::Hebergement->value;
                    $buckets[$key]['amounts'][$service] += (float) $payment->amount;
                    $buckets[$key]['total'] += (float) $payment->amount;
                }
            });

        return ['granularity' => $monthly ? 'mois' : 'jour', 'buckets' => array_values($buckets)];
    }

    /**
     * Activity of the sales services over [start, end) (by sale date): number of valid sales,
     * amount sold, average basket, and pool entries.
     *
     * @return array<string, array{sales: int, sold: float, basket: float, entries: int}>
     */
    public static function salesActivity(CarbonInterface $start, CarbonInterface $end): array
    {
        $activity = [];
        foreach (Department::salesServices() as $department) {
            $sales = Sale::query()->valid()->where('department', $department->value)->where('sold_at', '>=', $start)->where('sold_at', '<', $end);
            $count = (clone $sales)->count();
            $sold = (float) (clone $sales)->sum('total_amount');
            $entries = $department->countsEntries()
                ? (int) SaleLine::query()->where('counts_entry', true)->whereIn('sale_id', (clone $sales)->select('id'))->sum('quantity')
                : 0;
            $activity[$department->value] = ['sales' => $count, 'sold' => $sold, 'basket' => $count > 0 ? $sold / $count : 0.0, 'entries' => $entries];
        }

        return $activity;
    }

    /**
     * Services shown: those the hotel offers (Configuration → Hôtel — hébergement always), plus
     * one no longer offered that still has cash in a compared period (its history is kept).
     *
     * @param  array<string, array{amount: float, count: int}>  ...$totals
     * @return list<Department>
     */
    public static function visible(array ...$totals): array
    {
        return array_values(array_filter(Department::cases(), function (Department $department) use ($totals) {
            if ($department->isOffered()) {
                return true;
            }
            foreach ($totals as $set) {
                if (($set[$department->value]['amount'] ?? 0) > 0) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** Change in %, null when there is nothing to compare with. */
    public static function variation(float $current, float $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    private static function monthLabel(Carbon $month): string
    {
        $names = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

        return $names[$month->month - 1].' '.$month->format('Y');
    }
}
