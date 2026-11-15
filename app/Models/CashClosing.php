<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Audited;
use App\Support\Audit;
use App\Support\BusinessDay;
use App\Support\FinanceStats;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * « Clôture de caisse » (V2): at the end of a business day, the owner of a till (the receptionist
 * of the day — prise de service) declares what she hands over for every payment method; the
 * application compares it with what it expects (payments attributed to her that day − validated
 * expenses paid from her till) and the Gérant validates or contests it. Expected amounts are
 * always computed server side and snapshotted at submission. The day isn't locked (client
 * decision): if the figures move afterwards, the closing shows it. Nothing mass-assignable.
 */
class CashClosing extends Model
{
    use Audited;

    public const STATUS_SUBMITTED = 'soumise';

    public const STATUS_VALIDATED = 'validee';

    public const STATUS_CONTESTED = 'contestee';

    public const STATUSES = [
        self::STATUS_SUBMITTED => ['label' => 'À valider', 'color' => 'warning'],
        self::STATUS_VALIDATED => ['label' => 'Validée', 'color' => 'success'],
        self::STATUS_CONTESTED => ['label' => 'Contestée', 'color' => 'danger'],
    ];

    /** FCFA notes and coins for the cash count (billetage). */
    public const NOTES = [10000, 5000, 2000, 1000, 500];

    public const COINS = [500, 250, 200, 100, 50, 25, 10, 5];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'expected' => 'array',
            'declared' => 'array',
            'counted' => 'array',
            'total_expected' => 'decimal:2',
            'total_declared' => 'decimal:2',
            'total_difference' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'secondary';
    }

    /**
     * What `$userId`'s till should hold for business day `$date`, per method:
     * received (payments attributed to her, by paid_at — same rule as « Ma recette du jour »),
     * expenses (validated, paid from her till), expected = received − expenses.
     *
     * @return array<string, array{received: float, expenses: float, expected: float}>
     */
    public static function expectedFor(CarbonInterface $date, int $userId): array
    {
        [$from, $to] = BusinessDay::bounds($date);
        $received = collect(FinanceStats::cashByMethod($from, $to, $userId))->mapWithKeys(fn ($row) => [$row['method']->value => $row['amount']]);
        $outflows = Expense::tillOutflows($date, $userId);

        return collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $m) => [$m->value => [
            'received' => (float) ($received[$m->value] ?? 0),
            'expenses' => (float) ($outflows[$m->value] ?? 0),
            'expected' => (float) ($received[$m->value] ?? 0) - (float) ($outflows[$m->value] ?? 0),
        ]])->all();
    }

    /** Declared − expected per method. @return array<string, float> */
    public function differences(): array
    {
        return collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $m) => [
            $m->value => (float) ($this->declared[$m->value] ?? 0) - (float) ($this->expected[$m->value]['expected'] ?? 0),
        ])->all();
    }

    /** True when the day's figures moved since the closing was submitted (the day isn't locked). */
    public function figuresChanged(): bool
    {
        $now = self::expectedFor($this->business_date, $this->user_id);

        return collect($now)->contains(fn ($row, $method) => abs($row['expected'] - (float) ($this->expected[$method]['expected'] ?? 0)) >= 0.01);
    }

    /** Cash total of a note / coin count. @param array<string, int> $counted */
    public static function countedTotal(array $counted): int
    {
        $total = 0;
        foreach (['notes' => self::NOTES, 'coins' => self::COINS] as $kind => $values) {
            foreach ($values as $value) {
                $total += $value * max(0, (int) ($counted[$kind][$value] ?? 0));
            }
        }

        return $total;
    }

    /**
     * Submits (or re-submits, while not validated) the closing of `$owner`'s till for `$date`.
     *
     * @param  array<string, int>  $declared  per method
     */
    public static function submit(CarbonInterface $date, User $owner, array $declared, ?array $counted, ?string $comment, User $by): self
    {
        return DB::transaction(function () use ($date, $owner, $declared, $counted, $comment, $by) {
            $closing = static::query()->whereDate('business_date', $date->toDateString())->where('user_id', $owner->id)->lockForUpdate()->first();
            if ($closing && $closing->status === self::STATUS_VALIDATED) {
                throw ValidationException::withMessages(['closing' => 'La caisse du '.$date->format('d/m/Y').' est déjà clôturée et validée par le gérant.']);
            }

            $expected = self::expectedFor($date, $owner->id);
            $declared = collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $m) => [$m->value => (int) ($declared[$m->value] ?? 0)])->all();
            $totalExpected = array_sum(array_column($expected, 'expected'));
            $totalDeclared = array_sum($declared);

            $closing ??= new static;
            $closing->forceFill([
                'business_date' => $date->toDateString(),
                'user_id' => $owner->id,
                'expected' => $expected,
                'declared' => $declared,
                'counted' => $counted,
                'total_expected' => $totalExpected,
                'total_declared' => $totalDeclared,
                'total_difference' => $totalDeclared - $totalExpected,
                'comment' => $comment,
                'status' => self::STATUS_SUBMITTED,
                'submitted_by' => $by->id,
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_comment' => null,
            ])->save();

            return $closing;
        });
    }

    public function review(User $by, bool $validate, ?string $comment): void
    {
        DB::transaction(function () use ($by, $validate, $comment) {
            $locked = static::query()->lockForUpdate()->findOrFail($this->id);
            if ($locked->status !== self::STATUS_SUBMITTED) {
                throw ValidationException::withMessages(['closing' => 'Cette clôture a déjà été traitée ('.mb_strtolower($locked->statusLabel()).').']);
            }
            $locked->forceFill([
                'status' => $validate ? self::STATUS_VALIDATED : self::STATUS_CONTESTED,
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
                'review_comment' => $comment,
            ])->save();
            $this->setRawAttributes($locked->getAttributes(), true);
        });
    }

    // Journal d'activité

    public function auditFields(): array
    {
        return ['status' => 'Statut', 'total_expected' => 'Attendu', 'total_declared' => 'Déclaré', 'total_difference' => 'Écart', 'comment' => 'Commentaire', 'review_comment' => 'Commentaire du gérant', 'submitted_at' => 'Déclarée le'];
    }

    public function auditKey(): string
    {
        return 'cash_closing';
    }

    public function auditCategory(): string
    {
        return 'cloture';
    }

    public function auditName(): string
    {
        return 'clôture de caisse du '.$this->business_date?->format('d/m/Y').' ('.(User::query()->whereKey($this->user_id)->value('name') ?? '?').')';
    }

    public function auditMoneyFields(): array
    {
        return ['total_expected', 'total_declared', 'total_difference'];
    }

    public function auditDisplay(string $field, mixed $value): ?string
    {
        return $field === 'status' && is_string($value) ? (self::STATUSES[$value]['label'] ?? $value) : null;
    }

    public function auditAction(string $event, array $changes, array $dirty): ?array
    {
        $gap = ' — écart : '.Audit::money($this->total_difference);
        if ($event === 'created') {
            return ['cash_closing.submitted', 'Clôture de caisse déclarée : '.$this->auditName().$gap];
        }
        if ($event === 'updated' && in_array('status', $dirty, true) && $this->status === self::STATUS_VALIDATED) {
            return ['cash_closing.validated', 'Clôture de caisse validée : '.$this->auditName().$gap];
        }
        if ($event === 'updated' && in_array('status', $dirty, true) && $this->status === self::STATUS_CONTESTED) {
            return ['cash_closing.contested', 'Clôture de caisse contestée : '.$this->auditName().$gap.($this->review_comment ? ' — motif : '.$this->review_comment : '')];
        }

        return $event === 'updated' ? ['cash_closing.resubmitted', 'Clôture de caisse déclarée à nouveau : '.$this->auditName().$gap] : null;
    }
}
