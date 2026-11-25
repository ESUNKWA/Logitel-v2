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
 */
class CatalogItem extends Model
{
    use Audited;

    protected $fillable = ['category', 'name', 'price', 'counts_entry', 'is_active', 'position'];

    protected function casts(): array
    {
        return [
            'department' => Department::class,
            'price' => 'decimal:2',
            'counts_entry' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function auditFields(): array
    {
        return ['department' => 'Service', 'category' => 'Catégorie', 'name' => 'Nom', 'price' => 'Prix', 'counts_entry' => 'Compte une entrée', 'is_active' => 'Actif'];
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
