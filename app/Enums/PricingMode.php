<?php

namespace App\Enums;

use App\Models\RoomType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * How a stay is billed — one mode per room type rate (see config/hotel.php for the windows).
 * The departure time is always derived from the arrival + mode + duration, never entered.
 */
enum PricingMode: string
{
    case Heure = 'heure';
    case Journee = 'journee';
    case Nuitee = 'nuitee';
    case Sejour = 'sejour';

    /**
     * The only formula the public website mentions (client decision, 2026-10-19: for
     * advertising and reliability, the hourly, day and night formulas never appear on the site —
     * prices, texts, online booking requests). The management application keeps all four, and so
     * does the lobby QR-code page /demande (guests already on site).
     */
    public const WEBSITE = self::Sejour;

    /**
     * $stayUntil (« 12:00 », séjour of a room type ending the next day at a fixed time — see
     * stayUntilFor() / Reservation::$stay_until): « Séjour (jusqu'à 12h00) » instead of « Séjour (24 h) ».
     */
    public function getLabel(?string $stayUntil = null): string
    {
        return match ($this) {
            self::Heure => "À l'heure",
            self::Journee => 'Journée ('.self::dayHours().' h)',
            self::Nuitee => 'Nuitée',
            self::Sejour => $stayUntil ? "Séjour (jusqu'à ".self::clock($stayUntil).')' : 'Séjour ('.self::stayHours().' h)',
        };
    }

    /**
     * The fixed departure time of a séjour for this room type (« 12:00 », the hotel setting
     * hotel.stay_checkout_time) when the type is ticked « fin le lendemain à heure fixe », else
     * null (periods of 24 h). Files snapshot it (`stay_until`) so later changes never move them.
     */
    public static function stayUntilFor(?RoomType $roomType): ?string
    {
        return $roomType?->stay_fixed_checkout ? (string) config('hotel.stay_checkout_time', '12:00') : null;
    }

    /** « 12:00 » → « 12h00 ». */
    public static function clock(string $time): string
    {
        return str_replace(':', 'h', substr($time, 0, 5));
    }

    /**
     * "3 heures", "1 journée", "1 nuit", "2 périodes de 24 h".
     */
    public function durationLabel(int $duration, ?string $stayUntil = null): string
    {
        $plural = $duration > 1;

        return $duration.' '.match ($this) {
            self::Heure => $plural ? 'heures' : 'heure',
            self::Journee => $plural ? 'journées' : 'journée',
            self::Nuitee => $plural ? 'nuits' : 'nuit',
            self::Sejour => $stayUntil
                ? ($plural ? 'séjours' : 'séjour').' (sortie à '.self::clock($stayUntil).')'
                : ($plural ? 'périodes' : 'période').' de '.self::stayHours().' h',
        };
    }

    /**
     * Name of the duration unit, for form labels: "heures", "journées (6 h)", "nuits", "périodes de 24 h".
     */
    public function unitPlural(?string $stayUntil = null): string
    {
        return match ($this) {
            self::Heure => 'heures',
            self::Journee => 'journées ('.self::dayHours().' h)',
            self::Nuitee => 'nuits',
            self::Sejour => $stayUntil ? 'séjours (sortie à '.self::clock($stayUntil).')' : 'périodes de '.self::stayHours().' h',
        };
    }

    /**
     * Font Awesome 5 icon shown with the mode (each mode also has its own colour in admin.css,
     * class "mode--{value}"), so staff tell them apart at a glance.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Heure => 'fa-clock',
            self::Journee => 'fa-sun',
            self::Nuitee => 'fa-moon',
            self::Sejour => 'fa-calendar-alt',
        };
    }

    /**
     * Unit shown next to a rate: "/ heure", "/ journée", "/ nuit", "/ 24 h".
     */
    public function rateUnit(?string $stayUntil = null): string
    {
        return match ($this) {
            self::Heure => '/ heure',
            self::Journee => '/ journée',
            self::Nuitee => '/ nuit',
            self::Sejour => $stayUntil ? '/ séjour' : '/ '.self::stayHours().' h',
        };
    }

    /**
     * The room type column holding this mode's rate.
     */
    public function rateAttribute(): string
    {
        return match ($this) {
            self::Heure => 'hourly_rate',
            self::Journee => 'day_rate',
            self::Nuitee => 'night_rate',
            self::Sejour => 'stay_rate',
        };
    }

    /**
     * The room type's rate for this mode, or null when the type doesn't offer it.
     */
    public function rateFor(?RoomType $roomType): ?string
    {
        $rate = $roomType?->{$this->rateAttribute()};

        return $rate === null ? null : (string) $rate;
    }

    /**
     * Normalized start of the stay. A date-only nuitée (00:00, as sent by the date-based
     * reservations form) starts at the night window's opening time; otherwise the actual
     * arrival time is kept (minutes precision).
     */
    public function start(CarbonInterface $arrival): Carbon
    {
        $arrival = Carbon::instance($arrival)->second(0);

        if ($this === self::Nuitee && $arrival->format('H:i') === '00:00') {
            return $arrival->setTimeFromTimeString(config('hotel.night.from'));
        }

        return $arrival;
    }

    /**
     * Departure. A séjour with $stayUntil (« 12:00 ») ends that time N days after the arrival's
     * date, whatever the arrival time (client decision, 2026-11-13: 08:00 or 02:00 on Tuesday →
     * Wednesday 12:00 for 1 séjour); without it, after N periods of stay_min_hours.
     */
    public function end(CarbonInterface $start, int $duration, ?string $stayUntil = null): Carbon
    {
        $start = Carbon::instance($start);

        return match ($this) {
            self::Heure => $start->copy()->addHours($duration),
            self::Journee => $start->copy()->addHours($duration * self::dayHours()),
            self::Sejour => $stayUntil
                ? $start->copy()->startOfDay()->addDays($duration)->setTimeFromTimeString($stayUntil)
                : $start->copy()->addHours($duration * self::stayHours()),
            self::Nuitee => self::nightOf($start)->addDays($duration)->setTimeFromTimeString(config('hotel.night.until')),
        };
    }

    /**
     * Inverse of end(): the duration (in units of this mode) covering start → end.
     * Used when a stay is created from an explicit departure (date-based reservations form).
     */
    public function durationBetween(CarbonInterface $start, CarbonInterface $end, ?string $stayUntil = null): int
    {
        $start = Carbon::instance($start);
        $end = Carbon::instance($end);

        $duration = match ($this) {
            self::Heure => (int) ceil($start->diffInMinutes($end) / 60),
            self::Journee => (int) ceil($start->diffInMinutes($end) / (60 * self::dayHours())),
            self::Sejour => $stayUntil
                ? (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + ($end->format('H:i') > substr($stayUntil, 0, 5) ? 1 : 0)
                : (int) ceil($start->diffInMinutes($end) / (60 * self::stayHours())),
            self::Nuitee => (int) self::nightOf($start)->diffInDays($end->copy()->startOfDay()),
        };

        return max(1, $duration);
    }

    /**
     * The stay window and duration for an arrival, given either a duration (authoritative) or,
     * failing that, a departure to infer it from.
     *
     * @return array{0: Carbon, 1: Carbon, 2: int} [start, end, duration]
     */
    public function resolve(CarbonInterface $arrival, ?int $duration, ?CarbonInterface $departure = null, ?string $stayUntil = null): array
    {
        $start = $this->start($arrival);
        $stayUntil = $this === self::Sejour ? $stayUntil : null;

        if (! $duration && $departure) {
            $duration = $this->durationBetween($start, $departure, $stayUntil);
        }

        $duration = max(1, (int) $duration);

        return [$start, $this->end($start, $duration, $stayUntil), $duration];
    }

    /**
     * A nuitée can only start inside the night window (from 22:00 until 12:00 the next day).
     */
    public function canStartAt(CarbonInterface $moment): bool
    {
        if ($this !== self::Nuitee) {
            return true;
        }

        $time = $moment->format('H:i');

        return $time >= config('hotel.night.from') || $time < config('hotel.night.until');
    }

    /**
     * Date of the night a moment belongs to: before the window closes (12:00) it's still
     * the previous evening's night (an arrival at 02:00 leaves at 12:00 the same day).
     */
    private static function nightOf(Carbon $moment): Carbon
    {
        $night = $moment->copy()->startOfDay();

        return $moment->format('H:i') < config('hotel.night.until') ? $night->subDay() : $night;
    }

    private static function dayHours(): int
    {
        return (int) config('hotel.day_hours');
    }

    private static function stayHours(): int
    {
        return (int) config('hotel.stay_min_hours');
    }
}
