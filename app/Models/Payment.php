<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Models\Concerns\Audited;
use App\Models\Concerns\RecordsCreator;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use Audited, HasFactory, RecordsCreator;

    public function auditFields(): array
    {
        return ['method' => 'Mode', 'amount' => 'Montant', 'amount_received' => 'Reçu', 'change_given' => 'Rendu', 'paid_at' => 'Date'];
    }

    public function auditKey(): string
    {
        return 'payment';
    }

    public function auditCategory(): string
    {
        return 'encaissement';
    }

    public function auditName(): string
    {
        $file = $this->invoice?->reservation;

        return 'encaissement de '.Audit::money($this->amount).' ('.$this->method?->getLabel().') — facture '
            .($this->invoice?->number ?? '?').($file ? ' · '.$file->reference() : '');
    }

    public function auditReference(): ?string
    {
        return $this->invoice?->reservation?->reference() ?? $this->invoice?->number;
    }

    public function auditSubject(): array
    {
        return ['invoice', $this->invoice_id ? (int) $this->invoice_id : null];
    }

    public function auditMoneyFields(): array
    {
        return ['amount', 'amount_received', 'change_given'];
    }

    public function auditAction(string $event, array $changes, array $dirty): ?array
    {
        return match ($event) {
            'created' => ['payment.created', 'Encaissement : '.mb_substr($this->auditName(), mb_strlen('encaissement de '))],
            'deleted' => ['payment.deleted', 'Suppression d\'un '.$this->auditName()],
            default => null,
        };
    }

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

    /**
     * Payments of files billed in one formula (heure / journée / nuitée / séjour); null = all.
     */
    public function scopeOfPricingMode(Builder $query, ?PricingMode $mode): Builder
    {
        return $mode === null ? $query : $query->whereHas('invoice.reservation', fn (Builder $file) => $file->where('pricing_mode', $mode->value));
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

    /**
     * Whose sale it is: the receptionist of the business day it was recorded in (« prise de
     * service »), else whoever recorded it. `creator` stays who actually typed it.
     */
    public function attributedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attributed_to');
    }

    protected static function booted(): void
    {
        // Never from input (not fillable). Runs after RecordsCreator has set created_by.
        static::creating(function (Payment $payment) {
            $payment->attributed_to ??= DutyDay::attributeeAt(now()) ?? $payment->created_by;
        });
        static::created(fn (Payment $payment) => $payment->invoice->recalculateStatus());
        static::deleted(fn (Payment $payment) => $payment->invoice->recalculateStatus());
    }
}
