<?php

namespace App\Models;

use App\Enums\ExpenseSource;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\Audited;
use App\Support\Audit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * « Dépense » (V2): money going out. Requested with a mandatory receipt; the Gérant validates
 * or refuses it (an expense he records himself is validated at once). Only validated expenses
 * count: month's result, and — when paid from the till — what the receptionist hands over
 * (« Ma recette du jour »). Never deleted: refused / cancelled with a reason. Nothing mass-assignable
 * but the typed fields; status, people and dates are set by the methods below.
 */
class Expense extends Model
{
    use Audited;

    protected $fillable = ['business_date', 'expense_category_id', 'amount', 'source', 'beneficiary', 'description'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'amount' => 'decimal:2',
            'source' => ExpenseSource::class,
            'status' => ExpenseStatus::class,
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function attributedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attributed_to');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function reference(): string
    {
        return 'DEP-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('status', ExpenseStatus::Validee);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ExpenseStatus::EnAttente);
    }

    public function scopeBetweenDates(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->whereDate('business_date', '>=', $from->toDateString())->whereDate('business_date', '<=', $to->toDateString());
    }

    /** Validated expenses paid from `$userId`'s till on business day `$date`, per method. @return array<string, float> */
    public static function tillOutflows(CarbonInterface $date, int $userId): array
    {
        $sums = static::query()->validated()->whereDate('business_date', $date->toDateString())->where('attributed_to', $userId)
            ->whereIn('source', array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()))
            ->selectRaw('source, sum(amount) as amount')->groupBy('source')->pluck('amount', 'source');

        return collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $m) => [$m->value => (float) ($sums[$m->value] ?? 0)])->all();
    }

    public function isPending(): bool
    {
        return $this->status === ExpenseStatus::EnAttente;
    }

    /**
     * Records a new expense: validated at once when its author may validate, else « à valider ».
     */
    public static function request(array $attributes, string $receiptPath, string $receiptMime, User $by): self
    {
        $expense = new self($attributes);
        $expense->forceFill([
            'receipt_path' => $receiptPath,
            'receipt_mime' => $receiptMime,
            'requested_by' => $by->id,
            'status' => $by->can('expenses.validate') ? ExpenseStatus::Validee : ExpenseStatus::EnAttente,
        ]);
        if ($expense->status === ExpenseStatus::Validee) {
            $expense->forceFill(['decided_by' => $by->id, 'decided_at' => now()]);
        }
        // From the till of the receptionist on duty when recorded for its own day, else of the last
        // period of its date (prise de service), else the author's.
        $expense->attributed_to = DutyShift::expenseAttributee($expense->business_date, now()) ?? $by->id;
        $expense->save();

        return $expense;
    }

    public function approve(User $by): void
    {
        $this->decide($by, ExpenseStatus::Validee, null);
    }

    public function refuse(User $by, string $reason): void
    {
        $this->decide($by, ExpenseStatus::Refusee, $reason);
    }

    /**
     * Cancels a pending request (its author or a validator) or a validated expense (validator only,
     * reason required — checked by the request).
     */
    public function cancel(User $by, ?string $reason): void
    {
        DB::transaction(function () use ($by, $reason) {
            $locked = static::query()->lockForUpdate()->findOrFail($this->id);
            if (! in_array($locked->status, [ExpenseStatus::EnAttente, ExpenseStatus::Validee], true)) {
                throw ValidationException::withMessages(['expense' => 'Cette dépense est déjà '.mb_strtolower($locked->status->getLabel()).'.']);
            }
            $locked->forceFill(['status' => ExpenseStatus::Annulee, 'cancelled_by' => $by->id, 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            $this->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function decide(User $by, ExpenseStatus $status, ?string $reason): void
    {
        DB::transaction(function () use ($by, $status, $reason) {
            $locked = static::query()->lockForUpdate()->findOrFail($this->id);
            if (! $locked->isPending()) {
                throw ValidationException::withMessages(['expense' => 'Cette dépense a déjà été traitée ('.mb_strtolower($locked->status->getLabel()).').']);
            }
            $locked->forceFill(['status' => $status, 'decided_by' => $by->id, 'decided_at' => now(), 'decision_reason' => $reason])->save();
            $this->setRawAttributes($locked->getAttributes(), true);
        });
    }

    // Journal d'activité

    public function auditFields(): array
    {
        return ['status' => 'Statut', 'amount' => 'Montant', 'expense_category_id' => 'Catégorie', 'source' => 'Source', 'business_date' => 'Date',
            'beneficiary' => 'Bénéficiaire', 'description' => 'Description', 'decision_reason' => 'Motif du refus', 'cancel_reason' => 'Motif de l\'annulation'];
    }

    public function auditKey(): string
    {
        return 'expense';
    }

    public function auditCategory(): string
    {
        return 'depense';
    }

    public function auditName(): string
    {
        return 'dépense '.$this->reference().' : '.Audit::money($this->amount).' ('.($this->category?->name ?? '?').')';
    }

    public function auditReference(): ?string
    {
        return $this->reference();
    }

    public function auditMoneyFields(): array
    {
        return ['amount'];
    }

    public function auditDisplay(string $field, mixed $value): ?string
    {
        return match ($field) {
            'expense_category_id' => $value ? (ExpenseCategory::query()->whereKey($value)->value('name') ?? '#'.$value) : null,
            'business_date' => $value instanceof CarbonInterface ? $value->format('d/m/Y') : null,
            default => null,
        };
    }

    public function auditAction(string $event, array $changes, array $dirty): ?array
    {
        $name = $this->auditName();
        if ($event === 'created') {
            return $this->status === ExpenseStatus::Validee
                ? ['expense.created', 'Dépense enregistrée et validée : '.$name]
                : ['expense.requested', 'Demande de dépense : '.$name];
        }
        if ($event === 'updated' && in_array('status', $dirty, true)) {
            return match ($this->status) {
                ExpenseStatus::Validee => ['expense.approved', 'Dépense validée : '.$name],
                ExpenseStatus::Refusee => ['expense.refused', 'Dépense refusée : '.$name.($this->decision_reason ? ' — motif : '.$this->decision_reason : '')],
                ExpenseStatus::Annulee => ['expense.cancelled', 'Dépense annulée : '.$name.($this->cancel_reason ? ' — motif : '.$this->cancel_reason : '')],
                default => null,
            };
        }

        return null;
    }
}
