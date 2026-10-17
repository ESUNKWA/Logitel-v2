<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Models\Concerns\RecordsCreator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, RecordsCreator;

    protected $fillable = [
        'invoice_id',
        'amount',
        'method',
        'paid_at',
        'reference',
        'notes',
        'settlement',
        'amount_received',
        'change_given',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'amount_received' => 'decimal:2',
            'change_given' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * « Réservation » vs « Affectation » in the Recettes (client decision, 2026-10-17, option B):
     * a payment counts as a réservation's when its file was booked ahead (origin réservation)
     * and it was recorded **before the guest arrived**; everything else — affectations, and a
     * réservation's balance paid once the guest is in — counts as an affectation's. The moment
     * compared is `created_at` (when it was recorded), never the user-entered `paid_at`, so a
     * back-dated or same-minute entry can't move money from one side to the other. Arrival =
     * `checked_in_at`, or the planned `check_in` for stays started before that column existed.
     */
    public const KIND_BOOKING = Reservation::ORIGIN_RESERVATION;

    public const KIND_STAY = Reservation::ORIGIN_AFFECTATION;

    public function scopeBookingPayments(Builder $query): Builder
    {
        return $query->whereHas('invoice.reservation', fn (Builder $file) => self::constrainToBooking($file));
    }

    public function scopeStayPayments(Builder $query): Builder
    {
        return $query->whereDoesntHave('invoice.reservation', fn (Builder $file) => self::constrainToBooking($file));
    }

    /**
     * Payments of one kind (KIND_BOOKING / KIND_STAY); any other value leaves the query as is.
     */
    public function scopeOfKind(Builder $query, ?string $kind): Builder
    {
        return match ($kind) {
            self::KIND_BOOKING => $query->bookingPayments(),
            self::KIND_STAY => $query->stayPayments(),
            default => $query,
        };
    }

    private static function constrainToBooking(Builder $file): void
    {
        $started = [ReservationStatus::Enregistree->value, ReservationStatus::Terminee->value];

        $file->where('reservations.origin', Reservation::ORIGIN_RESERVATION)
            ->where(fn (Builder $w) => $w
                ->where(fn (Builder $a) => $a->whereNotNull('reservations.checked_in_at')
                    ->whereColumn('payments.created_at', '<', 'reservations.checked_in_at'))
                ->orWhere(fn (Builder $b) => $b->whereNull('reservations.checked_in_at')
                    ->where(fn (Builder $c) => $c->whereNotIn('reservations.status', $started)
                        ->orWhereColumn('payments.created_at', '<', 'reservations.check_in'))));
    }

    /**
     * Same rule as the scopes, for a loaded payment (lines, CSV). Load `invoice.reservation`.
     */
    public function isBookingPayment(): bool
    {
        $file = $this->invoice?->reservation;
        if (! $file || $file->origin !== Reservation::ORIGIN_RESERVATION) {
            return false;
        }

        $arrival = $file->checked_in_at
            ?? (in_array($file->status, [ReservationStatus::Enregistree, ReservationStatus::Terminee], true) ? $file->check_in : null);

        return $arrival === null || ($this->created_at !== null && $this->created_at->lt($arrival));
    }

    public function kind(): string
    {
        return $this->isBookingPayment() ? self::KIND_BOOKING : self::KIND_STAY;
    }

    protected static function booted(): void
    {
        static::created(fn (Payment $payment) => $payment->invoice->recalculateStatus());
        static::deleted(fn (Payment $payment) => $payment->invoice->recalculateStatus());
    }
}
