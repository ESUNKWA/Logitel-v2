<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The hotel's "journée commerciale": it starts at config('hotel.business_day_start') (06:00 by
 * default) and ends one minute before that time the next day. A moment before the start time
 * belongs to the previous calendar date (02:00 on Oct 8 → business day of Oct 7).
 * Bounds are [start, end) — use `>= start` and `< end` in queries.
 */
class BusinessDay
{
    public static function startTime(): string
    {
        return (string) config('hotel.business_day_start');
    }

    /**
     * "05:59" for a 06:00 start.
     */
    public static function endTime(): string
    {
        return Carbon::createFromFormat('H:i', self::startTime())->subMinute()->format('H:i');
    }

    /**
     * The business date (00:00) a moment belongs to.
     */
    public static function dateOf(CarbonInterface $moment): Carbon
    {
        $date = Carbon::instance($moment)->startOfDay();

        return $moment->format('H:i') < self::startTime() ? $date->subDay() : $date;
    }

    public static function today(): Carbon
    {
        return self::dateOf(now());
    }

    /**
     * @return array{0: Carbon, 1: Carbon} [start, end) of the business day of $date
     */
    public static function bounds(CarbonInterface $date): array
    {
        $start = Carbon::instance($date)->startOfDay()->setTimeFromTimeString(self::startTime());

        return [$start, $start->copy()->addDay()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function current(): array
    {
        return self::bounds(self::today());
    }

    /**
     * [start, end) of the business month containing $date (first business day 06:00 →
     * first business day of the next month 06:00).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function monthBounds(CarbonInterface $date): array
    {
        $first = Carbon::instance($date)->startOfMonth();

        return [self::bounds($first)[0], self::bounds($first->copy()->addMonth())[0]];
    }

    public static function contains(CarbonInterface $moment, ?CarbonInterface $date = null): bool
    {
        [$start, $end] = self::bounds($date ?? self::today());

        return $moment >= $start && $moment < $end;
    }
}
