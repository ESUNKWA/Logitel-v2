<?php

namespace App\Models;

use App\Exceptions\DeletionBlocked;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'hourly_rate',
        'day_rate',
        'night_rate',
        'stay_rate',
        'capacity_adults',
        'capacity_children',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'day_rate' => 'decimal:2',
            'night_rate' => 'decimal:2',
            'stay_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    protected static function booted(): void
    {
        // Deleting a type would take its rooms (and their history) with it.
        static::deleting(function (RoomType $roomType) {
            $count = $roomType->rooms()->count();
            if ($count > 0) {
                throw new DeletionBlocked("Le type « {$roomType->name} » a encore {$count} chambre(s) : changez leur type ou supprimez-les d'abord. Pour ne plus le proposer, décochez plutôt « Type actif ».");
            }
        });

        static::saving(function (RoomType $roomType) {
            if (blank($roomType->slug)) {
                $base = str($roomType->name)->slug()->toString() ?: 'type';
                $slug = $base;

                for ($i = 2; static::query()->where('slug', $slug)->whereKeyNot($roomType->getKey())->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }

                $roomType->slug = $slug;
            }
        });
    }
}
