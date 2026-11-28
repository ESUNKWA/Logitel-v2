<?php

namespace App\Models;

use App\Enums\Department;
use App\Models\Concerns\Audited;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An item a service sells (client request, 2026-11-25): a dish, a drink, a pool entry, a sunbed…
 * Its price is the one billed — never taken from the sale form; a sale line snapshots name and
 * price, so changing or deactivating an item never changes a past sale. Never deleted once sold.
 * Restaurant only: the days it is served (`days`, the weekly menu — client request 2026-11-28),
 * ISO numbers 1 = Monday … 7 = Sunday; none = every day (drinks, desserts…).
 */
class CatalogItem extends Model
{
    use Audited;

    /** ISO day numbers => short and long names (menu by day). */
    public const WEEKDAYS = [1 => ['Lun.', 'lundi'], 2 => ['Mar.', 'mardi'], 3 => ['Mer.', 'mercredi'], 4 => ['Jeu.', 'jeudi'], 5 => ['Ven.', 'vendredi'], 6 => ['Sam.', 'samedi'], 7 => ['Dim.', 'dimanche']];

    protected $fillable = ['category', 'name', 'price', 'counts_entry', 'is_active', 'position', 'days'];

    protected function casts(): array
    {
        return [
            'department' => Department::class,
            'price' => 'decimal:2',
            'counts_entry' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
            'days' => 'array',
        ];
    }

    /** @return list<int> the days it is served (empty: every day). */
    public function servedDays(): array
    {
        return array_values(array_map('intval', (array) ($this->days ?? [])));
    }

    /** On the menu of `$day` (a business day): always, unless the restaurant serves it on some days only. */
    public function servedOn(\Carbon\CarbonInterface $day): bool
    {
        $days = $this->servedDays();

        return ! $this->department?->servesByDay() || $days === [] || in_array($day->isoWeekday(), $days, true);
    }

    /** A dish of the weekly menu (served on some days only, restaurant). */
    public function isOnWeeklyMenu(): bool
    {
        return (bool) $this->department?->servesByDay() && $this->servedDays() !== [];
    }

    /** « Lun., Mer., Ven. » or « Tous les jours ». */
    public function daysLabel(): string
    {
        $days = $this->servedDays();

        return $days === [] ? 'Tous les jours' : implode(', ', array_map(fn (int $d) => self::WEEKDAYS[$d][0] ?? (string) $d, $days));
    }

    public function auditFields(): array
    {
        return ['department' => 'Service', 'category' => 'Catégorie', 'name' => 'Nom', 'price' => 'Prix', 'counts_entry' => 'Compte une entrée', 'is_active' => 'Actif', 'days' => 'Jours de service'];
    }

    public function auditKey(): string
    {
        return 'catalog_item';
    }

    public function auditCategory(): string
    {
        return 'configuration';
    }

    public function auditName(): string
    {
        return 'prestation « '.$this->name.' » ('.$this->department?->getLabel().')';
    }

    public function auditMoneyFields(): array
    {
        return ['price'];
    }

    public function auditDisplay(string $field, mixed $value): ?string
    {
        if ($field === 'days') {
            $days = is_string($value) ? (json_decode($value, true) ?? []) : (array) ($value ?? []);

            return $days === [] ? 'Tous les jours' : implode(', ', array_map(fn ($d) => self::WEEKDAYS[(int) $d][0] ?? (string) $d, $days));
        }

        return $field === 'department' ? ($value instanceof Department ? $value : Department::tryFrom((string) $value))?->getLabel() : null;
    }

    public function scopeOfDepartment(Builder $query, Department $department): Builder
    {
        return $query->where('department', $department->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('coalesce(category, \'\')')->orderBy('position')->orderBy('name');
    }

    public function isSold(): bool
    {
        return SaleLine::query()->where('catalog_item_id', $this->id)->exists();
    }

    public function priceLabel(): string
    {
        return Audit::money($this->price);
    }
}
