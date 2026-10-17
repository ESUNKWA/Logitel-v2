<?php

namespace App\Models;

use App\Enums\PricingMode;
use App\Support\BusinessDay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * A forfait for affectations: `duration` units of `pricing_mode` for a fixed `price`, for some
 * (or all) room types, optionally on some days of the week and/or within a period. The day is
 * the business day of the stay's start (BusinessDay::dateOf()). When several forfaits fit, the
 * lowest price wins, and only when it's below the normal price (rate × duration).
 * Réservations never get one (client decision, 2026-10-17).
 */
class SpecialRate extends Model
{
    protected $fillable = [
        'name',
        'pricing_mode',
        'duration',
        'price',
        'all_room_types',
        'days',
        'starts_on',
        'ends_on',
        'is_active',
    ];

    /** ISO day numbers → short French labels. */
    public const DAYS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];

    protected function casts(): array
    {
        return [
            'pricing_mode' => PricingMode::class,
            'duration' => 'integer',
            'price' => 'decimal:2',
            'all_room_types' => 'boolean',
            'days' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(RoomType::class);
    }

    /**
     * Active forfaits valid on this business date (day of the week + period) for this room type.
     */
    public function scopeApplicableOn(Builder $query, int $roomTypeId, CarbonInterface $date): Builder
    {
        $day = $date->toDateString();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $day))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day))
            ->where(fn (Builder $q) => $q->where('all_room_types', true)
                ->orWhereHas('roomTypes', fn (Builder $t) => $t->whereKey($roomTypeId)));
    }

    public function appliesOnDay(CarbonInterface $date): bool
    {
        return empty($this->days) || in_array($date->isoWeekday(), array_map('intval', $this->days), true);
    }

    /**
     * The forfait a stay gets: the cheapest one for this room type, mode and exact duration on
     * the business day of `$start`, provided it costs less than `$unitRate` × `$duration`.
     */
    public static function best(?int $roomTypeId, PricingMode $mode, int $duration, CarbonInterface $start, string $unitRate): ?self
    {
        if (! $roomTypeId || $duration < 1) {
            return null;
        }

        $date = BusinessDay::dateOf($start);
        $normal = bcmul($unitRate, (string) $duration, 2);

        return static::query()->applicableOn($roomTypeId, $date)
            ->where('pricing_mode', $mode->value)
            ->where('duration', $duration)
            ->orderBy('price')
            ->get()
            ->first(fn (self $rate) => $rate->appliesOnDay($date) && bccomp((string) $rate->price, $normal, 2) < 0);
    }

    /**
     * The forfaits of a business day for one room type, cheapest per mode and duration, only
     * those below the normal price: ['heure' => [2 => 5000.0, 3 => 7000.0]] (assignment preview).
     *
     * @return array<string, array<int, float>>
     */
    public static function menuFor(RoomType $roomType, CarbonInterface $date): array
    {
        return static::menusFor(collect([$roomType]), $date)[$roomType->id] ?? [];
    }

    /**
     * menuFor() for several room types in one query.
     *
     * @param  Collection<int, RoomType>  $roomTypes
     * @return array<int, array<string, array<int, float>>>
     */
    public static function menusFor(Collection $roomTypes, CarbonInterface $date): array
    {
        $day = $date->toDateString();
        $rates = static::query()->with('roomTypes:id')
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $day))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day))
            ->orderBy('duration')
            ->get()
            ->filter(fn (self $rate) => $rate->appliesOnDay($date));

        $menus = [];
        foreach ($roomTypes as $type) {
            foreach ($rates as $rate) {
                if (! $rate->all_room_types && ! $rate->roomTypes->contains('id', $type->id)) {
                    continue;
                }
                $unit = $rate->pricing_mode->rateFor($type);
                if ($unit === null || bccomp((string) $rate->price, bcmul((string) $unit, (string) $rate->duration, 2), 2) >= 0) {
                    continue;
                }
                $mode = $rate->pricing_mode->value;
                $current = $menus[$type->id][$mode][$rate->duration] ?? null;
                if ($current === null || (float) $rate->price < $current) {
                    $menus[$type->id][$mode][$rate->duration] = (float) $rate->price;
                }
            }
        }

        return $menus;
    }

    /**
     * menusFor() for a stay starting now (assignment modal, guest-request validation): the
     * business day of now, except for the nuitée, whose night may have started the day before
     * (an arrival before 12:00 belongs to the previous night — same rule as the model).
     *
     * @param  Collection<int, RoomType>  $roomTypes
     * @return array<int, array<string, array<int, float>>>
     */
    public static function menusForNow(Collection $roomTypes): array
    {
        $today = BusinessDay::dateOf(now());
        $menus = static::menusFor($roomTypes, $today);

        $nightDate = BusinessDay::dateOf(PricingMode::Nuitee->start(now()));
        if (! $nightDate->equalTo($today)) {
            $night = static::menusFor($roomTypes, $nightDate);
            foreach ($roomTypes as $type) {
                unset($menus[$type->id]['nuitee']);
                if (isset($night[$type->id]['nuitee'])) {
                    $menus[$type->id]['nuitee'] = $night[$type->id]['nuitee'];
                }
            }
        }

        return $menus;
    }

    /**
     * "Lun, Mar, Sam" / "Tous les jours".
     */
    public function daysLabel(): string
    {
        if (empty($this->days)) {
            return 'Tous les jours';
        }
        $days = array_map('intval', $this->days);
        sort($days);

        return implode(', ', array_map(fn (int $d) => self::DAYS[$d] ?? '?', $days));
    }

    /**
     * "du 01/11/2026 au 30/11/2026" / "à partir du …" / "jusqu'au …" / "Toujours".
     */
    public function periodLabel(): string
    {
        return match (true) {
            $this->starts_on && $this->ends_on => 'du '.$this->starts_on->format('d/m/Y').' au '.$this->ends_on->format('d/m/Y'),
            (bool) $this->starts_on => 'à partir du '.$this->starts_on->format('d/m/Y'),
            (bool) $this->ends_on => "jusqu'au ".$this->ends_on->format('d/m/Y'),
            default => 'Sans limite de dates',
        };
    }

    /**
     * Ended (its period is over) — shown apart in the list.
     */
    public function isPast(): bool
    {
        return $this->ends_on !== null && $this->ends_on->lt(BusinessDay::today());
    }
}
