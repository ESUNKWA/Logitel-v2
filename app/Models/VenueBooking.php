<?php

namespace App\Models;

use App\Enums\Department;
use App\Models\Concerns\Audited;
use App\Models\Concerns\RecordsCreator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A room booked for a meeting or an event (client request, 2026-11-25, step 2): one venue, one
 * day, from `start_at` to `end_at`, a formula (half day / full day — the venue's rate,
 * snapshotted), participants (never above the capacity), the client, and catalogue services
 * (coffee break, lunch, equipment…). Two bookings of a venue never overlap (checked under a lock
 * on the venue). Billed through its sale (department « evenements »): room hire line + services,
 * its invoice, an optional deposit and the balance — so the till, the prise de service, Recettes,
 * « À encaisser » and Pilotage work as for any sale. Status: option (quote) → confirmed;
 * cancelled (only while nothing is cashed, like any sale). Nothing mass-assignable.
 */
class VenueBooking extends Model
{
    use Audited, RecordsCreator;

    public const FORMULA_HALF = 'demi_journee';

    public const FORMULA_DAY = 'journee';

    public const FORMULAS = [self::FORMULA_HALF => 'Demi-journée', self::FORMULA_DAY => 'Journée'];

    public const STATUS_OPTION = 'option';

    public const STATUS_CONFIRMED = 'confirmee';

    public const STATUS_CANCELLED = 'annulee';

    public const STATUSES = [self::STATUS_OPTION => 'Option (devis)', self::STATUS_CONFIRMED => 'Confirmée', self::STATUS_CANCELLED => 'Annulée'];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'venue_rate' => 'decimal:2',
            'participants' => 'integer',
        ];
    }

    // ---- Journal d'activité ---------------------------------------------------------------

    public function auditFields(): array
    {
        return ['venue_id' => 'Salle', 'start_at' => 'Début', 'end_at' => 'Fin', 'formula' => 'Formule', 'participants' => 'Participants',
            'client_name' => 'Client', 'status' => 'Statut'];
    }

    public function auditKey(): string
    {
        return 'venue_booking';
    }

    public function auditCategory(): string
    {
        return 'vente';
    }

    public function auditName(): string
    {
        return 'réservation de salle du '.$this->start_at?->format('d/m/Y').' ('.($this->venue?->name ?? '?').' — '.$this->client_name.')';
    }

    public function auditDisplay(string $field, mixed $value): ?string
    {
        return match ($field) {
            'venue_id' => $value ? (Venue::query()->whereKey($value)->value('name') ?? '#'.$value) : null,
            'formula' => self::FORMULAS[$value] ?? null,
            'status' => self::STATUSES[$value] ?? null,
            default => null,
        };
    }

    public function auditAction(string $event, array $changes, array $dirty): ?array
    {
        return match (true) {
            $event === 'created' => ['venue_booking.created', 'Réservation de salle : '.$this->auditName()],
            $event === 'updated' && in_array('status', $dirty, true) && $this->status === self::STATUS_CONFIRMED => ['venue_booking.confirmed', 'Réservation de salle confirmée : '.$this->auditName()],
            $event === 'updated' && in_array('status', $dirty, true) && $this->status === self::STATUS_CANCELLED => ['venue_booking.cancelled', 'Réservation de salle annulée : '.$this->auditName()],
            $event === 'updated' => ['venue_booking.updated', 'Réservation de salle modifiée : '.$this->auditName()],
            default => null,
        };
    }

    // ---- Relations / queries ---------------------------------------------------------------

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isPast(): bool
    {
        return $this->end_at->lessThanOrEqualTo(now());
    }

    public function statusLabel(): string
    {
        return $this->isPast() && ! $this->isCancelled() ? 'Passée' : (self::STATUSES[$this->status] ?? $this->status);
    }

    public function formulaLabel(): string
    {
        return self::FORMULAS[$this->formula] ?? $this->formula;
    }

    public function reference(): string
    {
        return $this->sale?->reference() ?? 'SAL-'.$this->id;
    }

    /** The booking holding the venue over [start, end) (cancelled ones never do), if any. */
    public static function conflicting(int $venueId, CarbonInterface $start, CarbonInterface $end, ?int $exceptId = null): ?self
    {
        return static::query()->notCancelled()->where('venue_id', $venueId)
            ->where('start_at', '<', $end)->where('end_at', '>', $start)
            ->when($exceptId, fn (Builder $q) => $q->where('id', '!=', $exceptId))
            ->orderBy('start_at')->first();
    }

    // ---- Writing --------------------------------------------------------------------------

    /**
     * Books a venue. `$data`: venue_id, start_at, end_at, formula, participants, client_name,
     * client_phone, organization, title, notes, status (option / confirmed), quantities
     * (catalogue item id => quantity), discount, discount_reason. The venue is locked while
     * checking it is active, big enough and free.
     *
     * @param  array<string, mixed>  $data
     */
    public static function book(array $data, User $by): self
    {
        return DB::transaction(function () use ($data, $by) {
            $venue = self::lockedFreeVenue($data, null);

            $sale = Sale::record(Department::Evenements, $data['quantities'], self::label($data), null, $data['discount'], $data['discount_reason'] ?? null,
                [self::venueLine($venue, $data['formula'])]);

            $booking = new self;
            $booking->forceFill([
                'sale_id' => $sale->id,
                ...self::attributes($data, $venue),
                'status' => $data['status'] === self::STATUS_CONFIRMED ? self::STATUS_CONFIRMED : self::STATUS_OPTION,
                'confirmed_by' => $data['status'] === self::STATUS_CONFIRMED ? $by->id : null,
                'confirmed_at' => $data['status'] === self::STATUS_CONFIRMED ? now() : null,
            ])->save();

            return $booking->setRelation('sale', $sale)->setRelation('venue', $venue);
        });
    }

    /**
     * Changes the booking (venue, times, formula, participants, client, services, discount): the
     * sale is re-priced — never below what has already been cashed.
     *
     * @param  array<string, mixed>  $data
     */
    public function change(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $booking = static::query()->lockForUpdate()->findOrFail($this->id);
            if ($booking->isCancelled()) {
                throw ValidationException::withMessages(['venue_id' => 'Cette réservation est annulée : elle ne peut plus être modifiée.']);
            }
            $venue = self::lockedFreeVenue($data, $booking->id);

            $booking->sale->reprice($data['quantities'], [self::venueLine($venue, $data['formula'])], $data['discount'], $data['discount_reason'] ?? null, self::label($data));
            $booking->forceFill(self::attributes($data, $venue))->save();

            $this->setRawAttributes($booking->getAttributes(), true);

            return $this->setRelation('venue', $venue);
        });
    }

    public function confirm(User $by): void
    {
        if ($this->isCancelled()) {
            throw ValidationException::withMessages(['status' => 'Cette réservation est annulée.']);
        }
        if (! $this->isConfirmed()) {
            $this->forceFill(['status' => self::STATUS_CONFIRMED, 'confirmed_by' => $by->id, 'confirmed_at' => now()])->save();
        }
    }

    /** Cancels the booking and its sale (refused once something has been cashed, as any sale). */
    public function cancel(User $by, string $reason): void
    {
        DB::transaction(function () use ($by, $reason) {
            $booking = static::query()->lockForUpdate()->findOrFail($this->id);
            if ($booking->isCancelled()) {
                throw ValidationException::withMessages(['cancel_reason' => 'Cette réservation est déjà annulée.']);
            }
            $booking->sale->cancel($by, $reason);
            $booking->forceFill(['status' => self::STATUS_CANCELLED])->save();
            $this->setRawAttributes($booking->getAttributes(), true);
        });
    }

    /**
     * The venue, locked, checked active, big enough and free over the requested period.
     *
     * @param  array<string, mixed>  $data
     */
    private static function lockedFreeVenue(array $data, ?int $exceptId): Venue
    {
        $venue = Venue::query()->lockForUpdate()->find($data['venue_id']);
        if (! $venue || ! $venue->is_active) {
            throw ValidationException::withMessages(['venue_id' => 'Cette salle n\'est pas (ou plus) proposée.']);
        }
        if ((int) $data['participants'] > $venue->capacity) {
            throw ValidationException::withMessages(['participants' => 'La salle « '.$venue->name.' » accueille '.$venue->capacity.' personnes au plus.']);
        }
        if ($conflict = self::conflicting($venue->id, $data['start_at'], $data['end_at'], $exceptId)) {
            throw ValidationException::withMessages(['start_time' => 'La salle « '.$venue->name.' » est déjà réservée de '.$conflict->start_at->format('H\hi').' à '.$conflict->end_at->format('H\hi')
                .' ce jour-là ('.$conflict->client_name.').']);
        }

        return $venue;
    }

    /** @return array{label: string, unit_price: string, quantity: int} */
    private static function venueLine(Venue $venue, string $formula): array
    {
        return ['label' => 'Location salle '.$venue->name.' ('.mb_strtolower(self::FORMULAS[$formula]).')', 'unit_price' => $venue->rateFor($formula), 'quantity' => 1];
    }

    /** @param  array<string, mixed>  $data */
    private static function label(array $data): string
    {
        return trim(($data['title'] ?? '') !== '' ? $data['title'].' — '.$data['client_name'] : $data['client_name']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function attributes(array $data, Venue $venue): array
    {
        $text = fn (?string $v) => $v !== null && trim($v) !== '' ? trim($v) : null;

        return [
            'venue_id' => $venue->id,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'formula' => $data['formula'],
            'venue_rate' => $venue->rateFor($data['formula']),
            'participants' => (int) $data['participants'],
            'client_name' => trim($data['client_name']),
            'client_phone' => trim($data['client_phone']),
            'organization' => $text($data['organization'] ?? null),
            'title' => $text($data['title'] ?? null),
            'notes' => $text($data['notes'] ?? null),
        ];
    }
}
