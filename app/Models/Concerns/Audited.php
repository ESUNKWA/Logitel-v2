<?php

namespace App\Models\Concerns;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal d'activité (V2) for a model: one line when it's created, changed or deleted.
 * Only the whitelisted fields (auditFields(): field => French label) are ever recorded —
 * secrets can't leak by accident; an update touching none of them writes nothing.
 * A model may override auditKey(), auditCategory(), auditName(), auditReference(),
 * auditDisplay(), auditMoneyFields(), auditMasked() and auditAction()/auditDescription().
 */
trait Audited
{
    protected static function bootAudited(): void
    {
        static::created(fn (Model $m) => $m->writeAudit('created'));
        static::updated(fn (Model $m) => $m->writeAudit('updated'));
        static::deleted(fn (Model $m) => $m->writeAudit('deleted'));
    }

    /** @return array<string, string> */
    abstract public function auditFields(): array;

    abstract public function auditKey(): string;

    abstract public function auditCategory(): string;

    /** « Type de chambre « Deluxe » », « Chambre N°101 »… */
    abstract public function auditName(): string;

    public function auditReference(): ?string
    {
        return null;
    }

    /** @return list<string> */
    public function auditMoneyFields(): array
    {
        return [];
    }

    /** True: record which fields changed, never their values (personal data). */
    public function auditMasked(): bool
    {
        return false;
    }

    /** A field's value as text (foreign keys → names…); null = default formatting. */
    public function auditDisplay(string $field, mixed $value): ?string
    {
        return null;
    }

    /**
     * @param  list<array{label: string, old?: string|null, new?: string|null, masked?: bool}>  $changes
     * @return array{0: string, 1: string}|null [action, description], null = default
     */
    public function auditAction(string $event, array $changes, array $dirty): ?array
    {
        return null;
    }

    /** @return array{0: string, 1: int|null} [subject type, id] the line links to */
    public function auditSubject(): array
    {
        return [$this->auditKey(), (int) $this->getKey()];
    }

    public function writeAudit(string $event): void
    {
        $fields = $this->auditFields();
        $dirty = $event === 'updated' ? array_values(array_intersect(array_keys($this->getChanges()), array_keys($fields))) : [];
        if ($event === 'updated' && $dirty === []) {
            return;
        }

        $masked = $this->auditMasked();
        $money = $this->auditMoneyFields();
        $text = fn (string $field, mixed $value) => $this->auditDisplay($field, $value) ?? Audit::format($value, in_array($field, $money, true));
        $changes = [];

        foreach ($event === 'updated' ? $dirty : array_keys($fields) as $field) {
            $new = $event === 'deleted' ? null : $this->getAttribute($field);
            $old = $event === 'updated' ? $this->getOriginal($field) : ($event === 'deleted' ? $this->getAttribute($field) : null);
            if ($event !== 'updated' && ($event === 'deleted' ? $old : $new) === null) {
                continue;
            }
            $changes[] = $masked
                ? ['label' => $fields[$field], 'masked' => true]
                : ['label' => $fields[$field], 'old' => $event === 'created' ? null : $text($field, $old), 'new' => $event === 'deleted' ? null : $text($field, $new)];
        }

        [$action, $description] = $this->auditAction($event, $changes, $dirty) ?? [
            $this->auditKey().'.'.$event,
            match ($event) {
                'created' => 'Création : ',
                'deleted' => 'Suppression : ',
                default => 'Modification : ',
            }.$this->auditName(),
        ];

        [$subjectType, $subjectId] = $this->auditSubject();
        Audit::record($action, $this->auditCategory(), $description, $subjectType, $subjectId, $this->auditReference(), $changes);
    }
}
