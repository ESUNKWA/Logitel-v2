<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of the « Journal d'activité » (V2): written only by App\Support\Audit, never
 * updated nor deleted by the application (the model refuses both), nothing mass-assignable.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    /** Category key => label (filter of the journal page). */
    public const CATEGORIES = [
        'connexion' => 'Connexions',
        'dossier' => 'Affectations et réservations',
        'encaissement' => 'Encaissements',
        'depense' => 'Dépenses',
        'vente' => 'Ventes (restauration, bar, plage et piscine)',
        'cloture' => 'Clôtures de caisse',
        'client' => 'Clients',
        'configuration' => 'Configuration',
        'personnel' => 'Personnel et rôles',
        'corbeille' => 'Corbeille',
        'export' => 'Exports',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'changes' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d\'activité ne se modifie pas.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'activité ne se supprime pas.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The changes as one line of text (export). */
    public function changesText(): string
    {
        return collect($this->changes ?? [])->map(fn (array $c) => $c['label'].' : '.(! empty($c['masked']) ? 'modifié'
            : (($c['old'] ?? null) !== null ? $c['old'].' → ' : '').($c['new'] ?? '—')))->implode(' | ');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /**
     * Link to the file the line is about, when it still exists and the viewer may open it.
     */
    public function subjectUrl(?User $viewer): ?string
    {
        if (! $this->subject_id || ! $viewer) {
            return null;
        }

        return match ($this->subject_type) {
            'reservation' => ($file = Reservation::query()->find($this->subject_id))
                && $viewer->can($file->isStay() ? 'affectations.view' : 'reservations.view') ? $file->fileUrl() : null,
            'invoice' => $viewer->can('invoices.view') && Invoice::query()->whereKey($this->subject_id)->exists()
                ? route('admin.invoices.show', $this->subject_id) : null,
            default => null,
        };
    }

    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->where('created_at', '>=', $filters['start'])->where('created_at', '<', $filters['end'])
            ->when($filters['user'] === 'systeme', fn (Builder $q) => $q->whereNull('user_id'))
            ->when(ctype_digit($filters['user']), fn (Builder $q) => $q->where('user_id', (int) $filters['user']))
            ->when($filters['category'], fn (Builder $q, string $c) => $q->where('category', $c))
            ->when($filters['q'] !== '', function (Builder $q) use ($filters) {
                $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($filters['q'])).'%';
                $q->where(fn (Builder $w) => $w->whereRaw('lower(description) like ?', [$term])
                    ->orWhereRaw('lower(reference) like ?', [$term])
                    ->orWhereRaw('lower(user_name) like ?', [$term]));
            });
    }
}
