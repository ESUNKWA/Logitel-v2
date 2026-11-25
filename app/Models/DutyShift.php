<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Support\BusinessDay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * « Prise de service » by periods (client request, 2026-11-25): one receptionist on duty at a time,
 * from `started_at` until `ended_at` (null = on duty now). Works for every hotel: the Gérant alone
 * (no period: each sale stays its author's), one team a day (one period a day) or several teams
 * (one period each, each receptionist her own sales).
 *
 * Attribution (`attributed_to`): a payment / an affectation goes to whoever is on duty when it is
 * recorded (payments by `created_at`, stays by check-in), whoever typed it. What is recorded while
 * nobody is on duty goes to the next receptionist taking the service in the same business day
 * (the « gap »). A till expense goes to whoever is on duty when it is recorded for its own day,
 * else to the last period of its date (client decision). The Gérant corrects a period's
 * receptionist or designates one for a day without any: every sale of that window follows.
 */
class DutyShift extends Model
{
    use Audited;

    /**
     * Set by start(): what was cashed while nobody was on duty and became hers [total, rows].
     *
     * @var array{0: float, 1: int}
     */
    public array $gap = [0.0, 0];

    protected $fillable = [
        'user_id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    // ---- Journal d'activité ---------------------------------------------------------------

    public function auditFields(): array
    {
        return ['user_id' => 'Réceptionniste', 'ended_at' => 'Fin de service'];
    }

    public function auditKey(): string
    {
        return 'duty';
    }

    public function auditCategory(): string
    {
        return 'personnel';
    }

    public function auditName(): string
    {
        return 'période de service du '.$this->started_at?->format('d/m/Y à H\hi');
    }

    public function auditDisplay(string $field, mixed $value): ?string
    {
        return match ($field) {
            'user_id' => $value ? (User::query()->whereKey($value)->value('name') ?? '#'.$value) : null,
            'ended_at' => $value ? Carbon::parse($value)->format('d/m/Y H\hi') : '—',
            default => null,
        };
    }

    public function auditAction(string $event, array $changes, array $dirty): ?array
    {
        $who = User::query()->whereKey($this->user_id)->value('name') ?? '?';
        $name = fn (?int $id) => $id ? (User::query()->whereKey($id)->value('name') ?? '?') : null;

        if ($event === 'created') {
            return ['duty.taken', 'Prise de service : '.$who.' à partir du '.$this->started_at?->format('d/m/Y à H\hi')
                .($this->assigned_by ? ' (désignée par '.$name($this->assigned_by).')' : '')];
        }
        if ($event === 'updated' && in_array('user_id', $dirty, true)) {
            return ['duty.reassigned', 'Réceptionniste de la '.$this->auditName().' changée : '.$who
                .($this->assigned_by ? ' (désignée par '.$name($this->assigned_by).')' : '')];
        }
        if ($event === 'updated' && in_array('ended_at', $dirty, true) && $this->ended_at) {
            return ['duty.ended', 'Fin de service : '.$who.' à '.$this->ended_at->format('H\hi')
                .($this->ended_by && $this->ended_by !== $this->user_id ? ' (relève par '.$name($this->ended_by).')' : '')];
        }

        return null;
    }

    // ---- Relations / queries ---------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /** Periods overlapping [from, to). */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->where('started_at', '<', $to)
            ->where(fn (Builder $q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $from));
    }

    /** The receptionist on duty now (null: nobody). */
    public static function current(): ?self
    {
        return static::query()->with('user')->whereNull('ended_at')->orderByDesc('started_at')->first();
    }

    /** Periods overlapping a business day, in order. */
    public static function forDay(CarbonInterface $businessDate): Collection
    {
        [$start, $end] = BusinessDay::bounds($businessDate);

        return static::query()->with(['user', 'assignedBy', 'endedBy'])->overlapping($start, $end)->orderBy('started_at')->get();
    }

    /**
     * Who a sale recorded at `$moment` is attributed to: the receptionist on duty then, null when
     * nobody was (the caller then falls back to the author).
     */
    public static function attributeeAt(CarbonInterface $moment): ?int
    {
        return static::query()->where('started_at', '<=', $moment)
            ->where(fn (Builder $q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $moment))
            ->orderByDesc('started_at')->value('user_id');
    }

    /**
     * Whose till a till expense of `$businessDate`, recorded at `$recordedAt`, comes out of: the
     * receptionist on duty when it is recorded for its own day, else the last period of its date.
     */
    public static function expenseAttributee(CarbonInterface $businessDate, CarbonInterface $recordedAt): ?int
    {
        if (BusinessDay::dateOf($recordedAt)->toDateString() === $businessDate->toDateString()
            && ($onDuty = static::attributeeAt($recordedAt))) {
            return $onDuty;
        }
        [$start, $end] = BusinessDay::bounds($businessDate);

        return static::query()->overlapping($start, $end)->where('started_at', '<=', $recordedAt)
            ->orderByDesc('started_at')->value('user_id');
    }

    // ---- Taking / ending the service ------------------------------------------------------

    /**
     * `$user` goes on duty now: nothing to do if she already is; a colleague on duty is relieved
     * (her period ends now — the caller asked for the confirmation); when nobody was on duty, what
     * was recorded since the last period ended (within this business day) becomes hers.
     */
    public static function start(User $user): self
    {
        return DB::transaction(function () use ($user) {
            $now = now();
            // Serialise concurrent prises de service on the latest row.
            static::query()->orderByDesc('id')->lockForUpdate()->first();
            $open = static::query()->whereNull('ended_at')->orderByDesc('started_at')->lockForUpdate()->get();

            $mine = $open->firstWhere('user_id', $user->id);
            if ($mine && $open->count() === 1) {
                return $mine;
            }

            // Nobody on duty: the « gap » since the last period ended (this business day only).
            $gapFrom = null;
            if ($open->isEmpty()) {
                [$dayStart] = BusinessDay::bounds(BusinessDay::dateOf($now));
                $lastEnd = static::query()->where('ended_at', '<=', $now)->max('ended_at');
                $gapFrom = $lastEnd && Carbon::parse($lastEnd)->greaterThan($dayStart) ? Carbon::parse($lastEnd) : $dayStart;
            }

            foreach ($open as $shift) {
                $shift->forceFill(['ended_at' => $now, 'ended_by' => $user->id])->save();
            }

            $shift = new static(['user_id' => $user->id, 'started_at' => $now]);
            $shift->save();

            if ($gapFrom && $gapFrom->lessThan($now)) {
                $gapPayments = Payment::query()->where('created_at', '>=', $gapFrom)->where('created_at', '<', $now);
                $shift->gap = [(float) (clone $gapPayments)->sum('amount'), (clone $gapPayments)->count()];
                static::attributeWindow($gapFrom, $now, $user->id);
            }

            return $shift->setRelation('user', $user);
        });
    }

    /** Ends `$user`'s period (« Terminer mon service », or at log-out); false when she wasn't on duty. */
    public static function endFor(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            $shifts = static::query()->whereNull('ended_at')->where('user_id', $user->id)->lockForUpdate()->get();
            foreach ($shifts as $shift) {
                $shift->forceFill(['ended_at' => now(), 'ended_by' => $user->id])->save();
            }

            return $shifts->isNotEmpty();
        });
    }

    /** The Gérant ends a period left open (the receptionist left without ending it). */
    public function close(User $by): void
    {
        abort_unless($this->isOpen(), 404);
        $this->forceFill(['ended_at' => now(), 'ended_by' => $by->id])->save();
    }

    // ---- Gérant corrections ---------------------------------------------------------------

    /**
     * The Gérant changes the receptionist of this period: every sale of the period follows.
     */
    public function reassign(User $user, ?User $by = null): self
    {
        return DB::transaction(function () use ($user, $by) {
            $this->forceFill(['user_id' => $user->id, 'assigned_by' => $by?->id])->save();
            static::attributeWindow($this->started_at, $this->ended_at ?? now(), $user->id);

            return $this->setRelation('user', $user);
        });
    }

    /**
     * The Gérant designates the receptionist of a business day that has no period at all (today
     * or a past day): one period covering the whole day (today's stays open), every sale of the
     * day attributed to her. A day that has periods is corrected period by period.
     */
    public static function designateDay(CarbonInterface $businessDate, User $user, ?User $by = null): self
    {
        return DB::transaction(function () use ($businessDate, $user, $by) {
            [$start, $end] = BusinessDay::bounds($businessDate);
            static::query()->orderByDesc('id')->lockForUpdate()->first();
            if (static::query()->overlapping($start, $end)->exists()) {
                throw ValidationException::withMessages([
                    'business_date' => 'Cette journée a déjà des périodes de service : corrigez la réceptionniste de la période concernée.',
                ]);
            }

            $today = $businessDate->toDateString() === BusinessDay::today()->toDateString();
            $shift = new static(['user_id' => $user->id, 'started_at' => $start, 'ended_at' => $today ? null : $end]);
            $shift->assigned_by = $by?->id;
            $shift->save();

            static::attributeWindow($start, $today ? now() : $end, $user->id);

            return $shift->setRelation('user', $user);
        });
    }

    /**
     * Attributes to `$userId` every sale recorded in [from, to): payments (by `created_at`), stays
     * checked in, and till expenses whose rule now designates her (recomputed one by one).
     */
    private static function attributeWindow(CarbonInterface $from, CarbonInterface $to, int $userId): void
    {
        Payment::query()->where('created_at', '>=', $from)->where('created_at', '<', $to)->update(['attributed_to' => $userId]);
        Reservation::query()->where('checked_in_at', '>=', $from)->where('checked_in_at', '<', $to)->update(['attributed_to' => $userId]);

        $fromDate = BusinessDay::dateOf($from)->toDateString();
        $toDate = BusinessDay::dateOf($to->copy()->subSecond())->toDateString();
        Expense::query()->where('source', '!=', 'hors_caisse')
            ->where(fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('created_at', '>=', $from)->where('created_at', '<', $to))
                ->orWhere(fn (Builder $w) => $w->whereDate('business_date', '>=', $fromDate)->whereDate('business_date', '<=', $toDate)))
            ->get(['id', 'business_date', 'created_at', 'requested_by', 'attributed_to'])
            ->each(function (Expense $expense) {
                $attributee = static::expenseAttributee($expense->business_date, $expense->created_at) ?? $expense->requested_by;
                if ((int) $expense->attributed_to !== (int) $attributee) {
                    Expense::query()->whereKey($expense->id)->update(['attributed_to' => $attributee]);
                }
            });
    }

    // ---- Figures --------------------------------------------------------------------------

    /**
     * What was cashed during this period: [total, number of payment rows].
     *
     * @return array{0: float, 1: int}
     */
    public function salesSummary(): array
    {
        $payments = Payment::query()->where('created_at', '>=', $this->started_at)->where('created_at', '<', $this->ended_at ?? now()->addSecond());

        return [(float) (clone $payments)->sum('amount'), (clone $payments)->count()];
    }
}
