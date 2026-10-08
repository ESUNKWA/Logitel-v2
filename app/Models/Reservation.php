<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Concerns\RecordsCreator;
use App\Support\BusinessDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * One guest file: either an *affectation* (guest moves in now, room board — origin
 * "affectation") or a *réservation* (booked ahead, reservations module — origin "reservation").
 * When a réservation's guest arrives it is checked in and becomes a stay too: it then shows on
 * the affectations side, while staying in the reservations history as "honorée". The invoice
 * (created with the file) and any deposit therefore follow the guest from booking to stay.
 */
class Reservation extends Model
{
    use HasFactory, RecordsCreator;

    public const ORIGIN_RESERVATION = 'reservation';

    public const ORIGIN_AFFECTATION = 'affectation';

    protected $attributes = [
        'origin' => self::ORIGIN_RESERVATION,
    ];

    protected $fillable = [
        'guest_id',
        'room_id',
        'check_in',
        'check_out',
        'adults',
        'children',
        'status',
        'pricing_mode',
        'duration',
        'unit_rate',
        'origin',
        'total_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'status' => ReservationStatus::class,
            'pricing_mode' => PricingMode::class,
            'duration' => 'integer',
            'unit_rate' => 'decimal:2',
            'overdue_acknowledged_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_guaranteed' => 'boolean',
            'total_amount' => 'decimal:2',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Réservations: files booked ahead (whatever happened next).
     */
    public function scopeBookings(Builder $query): Builder
    {
        return $query->where('origin', self::ORIGIN_RESERVATION);
    }

    /**
     * Affectations: stays that have started (checked in or out), plus affectations cancelled
     * right after being made.
     */
    public function scopeStays(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('origin', self::ORIGIN_AFFECTATION)
            ->orWhereIn('status', [ReservationStatus::Enregistree, ReservationStatus::Terminee]));
    }

    public function isBooking(): bool
    {
        return $this->origin === self::ORIGIN_RESERVATION;
    }

    public function hasStarted(): bool
    {
        return in_array($this->status, [ReservationStatus::Enregistree, ReservationStatus::Terminee], true);
    }

    public function isStay(): bool
    {
        return $this->origin === self::ORIGIN_AFFECTATION || $this->hasStarted();
    }

    /**
     * A booking not started yet (en attente / confirmée).
     */
    public function isPendingBooking(): bool
    {
        return in_array($this->status, [ReservationStatus::EnAttente, ReservationStatus::Confirmee], true);
    }

    /**
     * Whether this file holds its room: a stay in progress always does; a pending booking only
     * once it's guaranteed (fully paid). Mirrors Room::constrainToConflicts().
     */
    public function holdsRoom(): bool
    {
        return $this->status === ReservationStatus::Enregistree || ($this->isPendingBooking() && $this->is_guaranteed);
    }

    /**
     * Recomputes `is_guaranteed` (never taken from input): a pending booking is guaranteed when
     * its invoice has nothing left to pay. Called by Invoice::recalculateStatus().
     */
    public function refreshGuarantee(): void
    {
        $guaranteed = $this->isPendingBooking()
            && $this->invoice !== null
            && bccomp($this->invoice->refresh()->remainingAmount(), '0', 2) <= 0;

        if ($guaranteed !== (bool) $this->is_guaranteed) {
            $this->forceFill(['is_guaranteed' => $guaranteed])->saveQuietly();
        }
    }

    /**
     * For a booking that doesn't hold its room: the stay or guaranteed booking that has taken
     * its slot, if any ("non garantie — chambre prise").
     */
    public function takenBy(): ?self
    {
        if ($this->holdsRoom() || ! $this->isPendingBooking() || ! $this->room) {
            return null;
        }

        return $this->room->conflictingReservation($this->check_in, $this->check_out, $this->id);
    }

    /**
     * Checked-in stays whose departure time has passed and whose alarm nobody acknowledged yet.
     */
    public function scopeOverdueCheckouts(Builder $query): Builder
    {
        return $query
            ->where('status', ReservationStatus::Enregistree)
            ->where('check_out', '<=', now())
            ->whereNull('overdue_acknowledged_at');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * The guest's request (sent from their phone) this affectation was validated from, if any.
     */
    public function stayRequest(): HasOne
    {
        return $this->hasOne(StayRequest::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * "3 heures × 5 000 FCFA" — the billing line shown on the stay and its invoice.
     */
    public function billingLabel(): string
    {
        return $this->pricing_mode->durationLabel($this->duration)
            .' × '.number_format((float) $this->unit_rate, 0, ',', ' ').' FCFA';
    }

    protected static function booted(): void
    {
        // Server-side source of truth for the stay window and the amount: check_out and
        // total_amount are always derived (mode + arrival + duration, rate × duration), never
        // trusted from input. An explicit check_out is only used to infer the duration when none
        // is given (date-based reservations form: arrival date → departure date, as nuitées).
        static::saving(function (Reservation $reservation) {
            if (! $reservation->check_in) {
                return;
            }

            $mode = $reservation->pricing_mode ?? PricingMode::Nuitee;
            [$start, $end, $duration] = $mode->resolve($reservation->check_in, $reservation->duration, $reservation->check_out);

            $reservation->pricing_mode = $mode;
            $reservation->check_in = $start;
            $reservation->check_out = $end;
            $reservation->duration = $duration;

            // Who did the check-in / check-out / cancellation, and when (never from input).
            if ($reservation->isDirty('status')) {
                $stamp = match ($reservation->status) {
                    ReservationStatus::Enregistree => 'checked_in',
                    ReservationStatus::Terminee => 'checked_out',
                    ReservationStatus::Annulee => 'cancelled',
                    default => null,
                };
                if ($stamp) {
                    $reservation->{$stamp.'_at'} = now();
                    $reservation->{$stamp.'_by'} = Auth::id();
                }
            }

            // A new departure time (stay extended/shortened) re-arms the checkout alarm.
            if ($reservation->exists && $reservation->isDirty('check_out')) {
                $reservation->overdue_acknowledged_at = null;
                $reservation->overdue_acknowledged_by = null;
            }

            if ($reservation->unit_rate !== null) {
                $reservation->total_amount = bcmul((string) $reservation->unit_rate, (string) $reservation->duration, 2);
            }
        });

        static::created(function (Reservation $reservation) {
            // Same transaction as the number allocation: the sequence row stays locked until the
            // invoice is inserted, and a failed insert doesn't burn a number.
            DB::transaction(fn () => $reservation->invoice()->create([
                'number' => Invoice::generateNumber(),
                'issued_at' => BusinessDay::today(), // invoices are dated by business day
                'amount' => $reservation->total_amount,
                'status' => InvoiceStatus::Impayee,
            ]));
        });

        static::updated(function (Reservation $reservation) {
            // Extending a stay or changing its rate re-bills it: the invoice follows the amount.
            if ($reservation->wasChanged('total_amount') && $reservation->invoice) {
                $reservation->invoice->update(['amount' => $reservation->total_amount]);
                $reservation->invoice->recalculateStatus();
            }

            if (! $reservation->wasChanged('status')) {
                return;
            }

            // Leaving the pending states (arrival, cancellation) ends the booking's guarantee.
            $reservation->refreshGuarantee();

            match ($reservation->status) {
                ReservationStatus::Enregistree => $reservation->room->update(['status' => RoomStatus::Occupee]),
                // Check-out, or cancelling a stay in progress, frees the room straight away
                // ("Libre", client request — no cleaning step). Only an occupied room is touched: one
                // put hors service meanwhile stays so. A booking cancelled before arrival never held
                // the room physically.
                ReservationStatus::Terminee, ReservationStatus::Annulee => $reservation->getOriginal('status') === ReservationStatus::Enregistree
                    && $reservation->room->status === RoomStatus::Occupee
                        ? $reservation->room->update(['status' => RoomStatus::Disponible])
                        : null,
                default => null,
            };
        });
    }
}
