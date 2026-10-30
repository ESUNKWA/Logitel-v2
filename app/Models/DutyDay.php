<?php

namespace App\Models;

use App\Support\BusinessDay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * « Prise de service » (client request, 2026-10-25): the receptionist of one business day. Every
 * sale of that day — payments (`payments.attributed_to`, by when they were recorded) and
 * affectations (`reservations.attributed_to`, by check-in) — is attributed to her, whoever typed
 * it, including what the outgoing colleague or the Gérant cashed before she arrived. One
 * receptionist per business day; only the Gérant may change it afterwards (duty.manage).
 */
class DutyDay extends Model
{
    protected $fillable = [
        'business_date',
        'user_id',
        'taken_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'taken_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public static function forDate(CarbonInterface $businessDate): ?self
    {
        return static::query()->with('user')->whereDate('business_date', $businessDate->toDateString())->first();
    }

    public static function today(): ?self
    {
        return static::forDate(BusinessDay::today());
    }

    /**
     * Who a sale recorded at `$moment` is attributed to: the receptionist of that business day,
     * null when nobody has taken the service yet (the caller then falls back to the author).
     */
    public static function attributeeAt(CarbonInterface $moment): ?int
    {
        return static::query()->whereDate('business_date', BusinessDay::dateOf($moment)->toDateString())->value('user_id');
    }

    /**
     * A receptionist takes the service of the current business day. Refused when a colleague
     * already holds it (one per day: the Gérant corrects it if needed).
     */
    public static function take(User $user): self
    {
        $date = BusinessDay::today();

        return DB::transaction(function () use ($user, $date) {
            $existing = static::query()->whereDate('business_date', $date->toDateString())->lockForUpdate()->first();
            if ($existing && $existing->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'duty' => ($existing->user?->name ?? 'Une collègue').' a déjà pris le service de cette journée. Seul le Gérant peut changer la réceptionniste du jour.',
                ]);
            }

            return $existing ?? static::assign($date, $user);
        });
    }

    /**
     * Sets (or replaces) the receptionist of a business day and attributes all of that day's
     * sales to her: payments recorded during the day and stays checked in during it.
     */
    public static function assign(CarbonInterface $businessDate, User $user, ?User $by = null): self
    {
        return DB::transaction(function () use ($businessDate, $user, $by) {
            $duty = static::query()->whereDate('business_date', $businessDate->toDateString())->lockForUpdate()->first()
                ?? new static(['business_date' => $businessDate->toDateString()]);
            $duty->user_id = $user->id;
            $duty->taken_at = now();
            $duty->assigned_by = $by?->id;
            $duty->save();

            [$start, $end] = BusinessDay::bounds($businessDate);
            Payment::query()->where('created_at', '>=', $start)->where('created_at', '<', $end)
                ->update(['attributed_to' => $user->id]);
            Reservation::query()->where('checked_in_at', '>=', $start)->where('checked_in_at', '<', $end)
                ->update(['attributed_to' => $user->id]);

            return $duty->setRelation('user', $user);
        });
    }

    /**
     * Sales attributed to this day's receptionist: [cash total, number of payments rows].
     *
     * @return array{0: float, 1: int}
     */
    public function salesSummary(): array
    {
        [$start, $end] = BusinessDay::bounds($this->business_date);
        $payments = Payment::query()->where('created_at', '>=', $start)->where('created_at', '<', $end);

        return [(float) (clone $payments)->sum('amount'), (clone $payments)->count()];
    }
}
