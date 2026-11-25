<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * A period of business days read from the query string, shared by « Recettes » and « Pilotage »:
 * a shortcut (?periode=), one day (?jour=Y-m-d) or a custom range (?du=&au=). Future dates are
 * brought back to today, a range is capped at 366 days, unknown values fall back to today.
 * Values are only ever read as plain strings (a forged array is ignored).
 */
final class PeriodFilter
{
    /**
     * @param  array<string, string>  $shortcuts  shortcut => label (unknown shortcuts fall back to today)
     * @return array{period: string, from: Carbon, to: Carbon}
     */
    public static function fromRequest(Request $request, array $shortcuts): array
    {
        $today = BusinessDay::today();
        $text = fn (string $key): string => is_string($value = $request->query($key)) ? $value : '';
        $date = function (string $key) use ($text, $today): ?Carbon {
            try {
                $d = $text($key) !== '' ? Carbon::createFromFormat('!Y-m-d', $text($key)) : null;
            } catch (Throwable) {
                return null;
            }

            return $d?->min($today);
        };

        $period = $text('periode');
        if ($text('du') !== '' || $text('au') !== '') {
            $from = $date('du') ?? $date('au') ?? $today;
            $to = $date('au') ?? $from;
            [$from, $to] = $from->greaterThan($to) ? [$to, $from] : [$from, $to];
            $from = $from->max($to->copy()->subDays(365));
            $period = 'personnalisee';
        } elseif ($text('jour') !== '' && ($day = $date('jour'))) {
            [$from, $to, $period] = [$day, $day->copy(), 'jour'];
        } else {
            $period = array_key_exists($period, $shortcuts) ? $period : 'aujourdhui';
            [$from, $to] = match ($period) {
                'hier' => [$today->copy()->subDay(), $today->copy()->subDay()],
                'semaine' => [$today->copy()->startOfWeek(), $today->copy()],
                'mois' => [$today->copy()->startOfMonth(), $today->copy()],
                'mois-dernier' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
                'annee' => [$today->copy()->startOfYear(), $today->copy()],
                default => [$today->copy(), $today->copy()],
            };
        }

        return ['period' => $period, 'from' => $from, 'to' => $to];
    }
}
