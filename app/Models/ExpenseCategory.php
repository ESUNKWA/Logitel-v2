<?php

namespace App\Models;

use App\Exceptions\DeletionBlocked;
use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Category of an expense (« Carburant », « Salaires »…), managed by the Gérant. A category in
 * use is never deleted (the history keeps it): it is deactivated instead.
 */
class ExpenseCategory extends Model
{
    use Audited;

    protected $fillable = ['name', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'position' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(function (ExpenseCategory $category) {
            if ($category->expenses()->exists()) {
                throw new DeletionBlocked('La catégorie « '.$category->name.' » est utilisée par des dépenses : désactivez-la plutôt.');
            }
        });
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    public function auditFields(): array
    {
        return ['name' => 'Nom', 'position' => 'Ordre', 'is_active' => 'Active'];
    }

    public function auditKey(): string
    {
        return 'expense_category';
    }

    public function auditCategory(): string
    {
        return 'depense';
    }

    public function auditName(): string
    {
        return 'catégorie de dépense « '.$this->name.' »';
    }
}
