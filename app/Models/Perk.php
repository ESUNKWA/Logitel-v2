<?php

namespace App\Models;

use App\Enums\PricingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * An included perk (Configuration → Avantages inclus, client request 2026-10-25), e.g. « Accès
 * piscine gratuit » for the SUITE type in séjour. Granted to some (or all) room types, for some
 * pricing modes (`modes` null = every formula). Affectations and réservations both get it: each
 * file keeps a snapshot of the labels (Reservation::$perks), printed on the receipt.
 */
class Perk extends Model
{
    protected $fillable = [
        'label',
        'all_room_types',
        'modes',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'all_room_types' => 'boolean',
            'modes' => 'array',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(RoomType::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function appliesToMode(PricingMode $mode): bool
    {
        return empty($this->modes) || in_array($mode->value, $this->modes, true);
    }

    public function appliesToRoomType(int $roomTypeId): bool
    {
        return $this->all_room_types || $this->roomTypes->contains('id', $roomTypeId);
    }

    /**
     * « Toutes les formules » or « Séjour, Nuitée ».
     */
    public function modesLabel(): string
    {
        if (empty($this->modes)) {
            return 'Toutes les formules';
        }

        return collect(PricingMode::cases())
            ->filter(fn (PricingMode $mode) => in_array($mode->value, $this->modes, true))
            ->map->getLabel()
            ->implode(', ');
    }

    /**
     * Labels of the active perks a file of this room type in this mode gets, in display order.
     *
     * @return list<string>
     */
    public static function labelsFor(?int $roomTypeId, PricingMode $mode): array
    {
        if (! $roomTypeId) {
            return [];
        }

        return static::query()->active()->ordered()
            ->where(fn (Builder $q) => $q->where('all_room_types', true)
                ->orWhereHas('roomTypes', fn (Builder $t) => $t->whereKey($roomTypeId)))
            ->get()
            ->filter->appliesToMode($mode)
            ->pluck('label')
            ->values()
            ->all();
    }

    /**
     * labelsFor() for several room types and every mode in one query, for the desk forms' live
     * hint: [roomTypeId => ['sejour' => ['Accès piscine gratuit']]] (modes without perk omitted).
     *
     * @param  Collection<int, RoomType>|iterable<RoomType>  $roomTypes
     * @return array<int, array<string, list<string>>>
     */
    public static function menusFor(iterable $roomTypes): array
    {
        $perks = static::query()->active()->ordered()->with('roomTypes:id')->get();
        $menus = [];

        foreach ($roomTypes as $type) {
            foreach (PricingMode::cases() as $mode) {
                $labels = $perks->filter(fn (self $perk) => $perk->appliesToRoomType($type->id) && $perk->appliesToMode($mode))
                    ->pluck('label')->values()->all();
                if ($labels !== []) {
                    $menus[$type->id][$mode->value] = $labels;
                }
            }
        }

        return $menus;
    }
}
