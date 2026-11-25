<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A room to hire for meetings & events (« salle »), managed by the Gérant (catalog.manage): its
 * capacity and its half-day / full-day rates — the rate billed is snapshotted on each booking.
 * Never deleted once booked (deactivate it).
 */
class Venue extends Model
{
    use Audited;

    protected $fillable = ['name', 'capacity', 'half_day_rate', 'day_rate', 'description', 'is_active', 'position'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'half_day_rate' => 'decimal:2',
            'day_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function auditFields(): array
    {
        return ['name' => 'Nom', 'capacity' => 'Capacité', 'half_day_rate' => 'Tarif demi-journée', 'day_rate' => 'Tarif journée', 'is_active' => 'Active'];
    }

    public function auditKey(): string
    {
        return 'venue';
    }

    public function auditCategory(): string
    {
        return 'configuration';
    }

    public function auditName(): string
    {
        return 'salle « '.$this->name.' »';
    }

    public function auditMoneyFields(): array
    {
        return ['half_day_rate', 'day_rate'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(VenueBooking::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    /** The rate of a formula (VenueBooking::FORMULA_*). */
    public function rateFor(string $formula): string
    {
        return (string) ($formula === VenueBooking::FORMULA_DAY ? $this->day_rate : $this->half_day_rate);
    }
}
