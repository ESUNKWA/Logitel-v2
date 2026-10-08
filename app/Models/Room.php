<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Enums\RoomAmenity;
use App\Enums\RoomStatus;
use App\Exceptions\DeletionBlocked;
use App\Support\BusinessDay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class Room extends Model
{
    use HasFactory;

    /**
     * Display order of room states (board sections, room list grouping): state key => [label,
     * Bootstrap color]. "reservee" (see isReserved()) and "demande" (see isRequested()) are computed,
     * the others are RoomStatus values.
     */
    public const DISPLAY_STATES = [
        'occupee' => ['Occupées', 'danger'],
        'disponible' => ['Libres', 'success'],
        'reservee' => ['Réservées', 'info'],
        'demande' => ['Demandes clients', 'primary'],
        'nettoyage' => ['En nettoyage', 'warning'],
        'hors_service' => ['Hors service', 'secondary'],
    ];

    protected $fillable = [
        'room_type_id',
        'number',
        'floor',
        'status',
        'notes',
        // Public presentation (room page on the website)
        'is_published',
        'public_name',
        'tagline',
        'surface',
        'beds',
        'bathrooms',
        'description',
        'amenities',
        'features',
        'rules',
        'cancellation_policy',
    ];

    /**
     * Fields of the public presentation — editable with rooms.manage only (like the photos).
     */
    public const PRESENTATION_FIELDS = [
        'is_published', 'public_name', 'tagline', 'surface', 'beds', 'bathrooms',
        'description', 'amenities', 'features', 'rules', 'cancellation_policy',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'is_published' => 'boolean',
            'surface' => 'integer',
            'beds' => 'integer',
            'bathrooms' => 'integer',
            'amenities' => AsEnumCollection::of(RoomAmenity::class),
            'features' => 'array',
            'rules' => 'array',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function stayRequests(): HasMany
    {
        return $this->hasMany(StayRequest::class);
    }

    /**
     * A guest's request for this room, waiting for the réception (pending, not expired): the
     * room is held for them (see StayRequest).
     */
    public function pendingRequest(): HasOne
    {
        return $this->hasOne(StayRequest::class)->holding()->latest('id');
    }

    /**
     * Photos, cover first (max RoomImage::MAX_PER_ROOM).
     */
    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('position')->orderBy('id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Room $room) {
            // A room with stays keeps its history: take it out of service instead.
            $count = $room->reservations()->count();
            if ($count > 0) {
                throw new DeletionBlocked("La chambre N°{$room->number} a {$count} séjour(s) ou réservation(s) : elle ne peut pas être supprimée, pour conserver l'historique et les factures. Passez-la plutôt « Hors service ».");
            }

            // The FK cascade would delete the rows but not the files: delete them one by one.
            $room->images()->get()->each->delete();
        });
    }

    /**
     * The checked-in reservation currently occupying the room (at most one: overlaps are prevented).
     */
    public function currentStay(): HasOne
    {
        return $this->hasOne(Reservation::class)->where('status', ReservationStatus::Enregistree);
    }

    /**
     * A reservation for this room starting during the current business day (or already started)
     * that hasn't been checked in yet: the room is promised to a guest even though its physical
     * status is still "disponible". "Today" is the journée commerciale (see BusinessDay).
     */
    public function pendingArrival(): HasOne
    {
        return $this->hasOne(Reservation::class)
            ->whereIn('status', [ReservationStatus::EnAttente, ReservationStatus::Confirmee])
            ->where('is_guaranteed', true)
            ->where('check_in', '<', BusinessDay::current()[1])
            ->where('check_out', '>', now());
    }

    /**
     * An unpaid (non-guaranteed) booking expected during the current business day: it doesn't
     * hold the room, but the board shows it so the réception knows.
     */
    public function unguaranteedArrival(): HasOne
    {
        return $this->hasOne(Reservation::class)->ofMany(['check_in' => 'min'], function (Builder $query) {
            $query->whereIn('status', [ReservationStatus::EnAttente, ReservationStatus::Confirmee])
                ->where('is_guaranteed', false)
                ->where('check_in', '<', BusinessDay::current()[1])
                ->where('check_out', '>', now());
        });
    }

    /**
     * The earliest future reservation not yet checked in (room is free tonight but booked later).
     */
    public function nextArrival(): HasOne
    {
        return $this->hasOne(Reservation::class)->ofMany(['check_in' => 'min'], function (Builder $query) {
            $query->whereIn('status', [ReservationStatus::EnAttente, ReservationStatus::Confirmee])
                ->where('is_guaranteed', true)
                ->where('check_in', '>=', BusinessDay::current()[1]);
        });
    }

    /**
     * Physically free and not assigned to anyone for tonight: the rooms staff can hand out now.
     */
    public function scopeAvailableNow(Builder $query): Builder
    {
        return $query->where('status', RoomStatus::Disponible)->whereDoesntHave('pendingArrival')->whereDoesntHave('pendingRequest');
    }

    /**
     * Physically free but already assigned to a guest for tonight (arrival pending).
     */
    public function scopeReservedNow(Builder $query): Builder
    {
        return $query->where('status', RoomStatus::Disponible)->whereHas('pendingArrival');
    }

    public function isReserved(): bool
    {
        return $this->status === RoomStatus::Disponible && $this->pendingArrival !== null;
    }

    /**
     * Physically free, held by a guest's request waiting for the réception (and not reserved).
     */
    public function scopeRequestedNow(Builder $query): Builder
    {
        return $query->where('status', RoomStatus::Disponible)->whereDoesntHave('pendingArrival')->whereHas('pendingRequest');
    }

    public function isRequested(): bool
    {
        return $this->status === RoomStatus::Disponible && ! $this->isReserved() && $this->pendingRequest !== null;
    }

    /**
     * Name shown on the website: the custom one, else the room type's.
     */
    public function displayName(): string
    {
        return $this->public_name ?: (string) $this->roomType?->name;
    }

    public function displayState(): string
    {
        return match (true) {
            $this->isReserved() => 'reservee',
            $this->isRequested() => 'demande',
            default => $this->status->value,
        };
    }

    /**
     * Rooms ordered by state (DISPLAY_STATES order), then naturally by number (2 before 10).
     *
     * @param  Collection<int, Room>  $rooms
     * @return Collection<int, Room>
     */
    public static function sortForDisplay(Collection $rooms): Collection
    {
        $order = array_flip(array_keys(self::DISPLAY_STATES));

        return $rooms->sort(function (Room $a, Room $b) use ($order) {
            return ($order[$a->displayState()] ?? 99) <=> ($order[$b->displayState()] ?? 99)
                ?: strnatcasecmp((string) $a->number, (string) $b->number);
        })->values();
    }

    public function isAvailableNow(): bool
    {
        return $this->status === RoomStatus::Disponible && $this->pendingArrival === null && $this->pendingRequest === null;
    }

    /**
     * Rooms with no stay/booking — nor guest request (StayRequest) — holding them over
     * [start, end): see constrainToConflicts().
     * check_in/check_out are timestamps; always compare against Carbon instances (bound as
     * "Y-m-d H:i:s", the format the datetime cast stores on both SQLite and PostgreSQL),
     * never against raw input strings in another format.
     */
    public function scopeAvailableBetween(Builder $query, CarbonInterface|string $start, CarbonInterface|string $end, ?int $excludeReservationId = null, ?int $excludeRequestId = null): Builder
    {
        return $query
            ->whereDoesntHave('reservations', fn (Builder $reservations) => self::constrainToConflicts(
                $reservations, Carbon::parse($start), Carbon::parse($end), $excludeReservationId,
            ))
            ->whereDoesntHave('stayRequests', fn (Builder $requests) => StayRequest::constrainToConflicts(
                $requests, Carbon::parse($start), Carbon::parse($end), $excludeRequestId,
            ));
    }

    /**
     * The single definition of "holds the room over [start, end)", to the minute:
     * - who holds a room: a stay in progress (enregistrée) always; a booking (en attente /
     *   confirmée) only once guaranteed — fully paid (`is_guaranteed`, see
     *   Reservation::refreshGuarantee()); cancelled, finished and unpaid bookings never do;
     * - the cleaning buffer (config hotel.cleaning_buffer_minutes) must separate two stays, so
     *   the requested period is widened by it on both sides;
     * - a stay in progress whose departure time has passed still holds the room until its real
     *   check-out (the guest is still inside), so it blocks every later period too.
     */
    public static function constrainToConflicts(Builder $reservations, CarbonInterface $start, CarbonInterface $end, ?int $excludeReservationId = null): Builder
    {
        $buffer = (int) config('hotel.cleaning_buffer_minutes', 0);

        return $reservations
            ->where(fn (Builder $q) => $q
                ->where('status', ReservationStatus::Enregistree)
                ->orWhere(fn (Builder $booking) => $booking
                    ->whereIn('status', [ReservationStatus::EnAttente, ReservationStatus::Confirmee])
                    ->where('is_guaranteed', true)))
            ->where('check_in', '<', Carbon::instance($end)->addMinutes($buffer))
            ->where(fn (Builder $q) => $q
                ->where('check_out', '>', Carbon::instance($start)->subMinutes($buffer))
                ->orWhere(fn (Builder $overdue) => $overdue
                    ->where('status', ReservationStatus::Enregistree)
                    ->where('check_out', '<=', now())))
            ->when($excludeReservationId, fn (Builder $q, int $id) => $q->where('id', '!=', $id));
    }

    /**
     * The first stay/booking holding this room over [start, end), if any.
     */
    public function conflictingReservation(CarbonInterface $start, CarbonInterface $end, ?int $excludeReservationId = null): ?Reservation
    {
        return self::constrainToConflicts($this->reservations()->getQuery(), $start, $end, $excludeReservationId)
            ->orderBy('check_in')
            ->first();
    }

    /**
     * The guest request (pending, not expired) holding this room over [start, end), if any.
     */
    public function conflictingRequest(CarbonInterface $start, CarbonInterface $end, ?int $excludeRequestId = null): ?StayRequest
    {
        return StayRequest::constrainToConflicts($this->stayRequests()->getQuery(), $start, $end, $excludeRequestId)
            ->orderBy('check_in')
            ->first();
    }

    /**
     * Why the room can't be taken over [start, end) — a stay/booking or a guest request holding
     * it — or null when it's free. The one check every write path goes through.
     */
    public function availabilityError(CarbonInterface $start, CarbonInterface $end, ?int $excludeReservationId = null, ?int $excludeRequestId = null): ?string
    {
        if ($conflict = $this->conflictingReservation($start, $end, $excludeReservationId)) {
            return static::conflictMessage($conflict, $start, $end);
        }

        if ($request = $this->conflictingRequest($start, $end, $excludeRequestId)) {
            return "La chambre N°{$this->number} est retenue par la demande d'un client (N°{$request->id}, "
                .$request->fullName().') en attente jusqu\'à '.$request->expires_at->format('H\hi')
                .' : validez ou refusez cette demande d\'abord (menu « Demandes clients »).';
        }

        return null;
    }

    public static function conflictMessage(Reservation $conflict, ?CarbonInterface $start = null, ?CarbonInterface $end = null): string
    {
        $room = $conflict->room?->number;
        $buffer = (int) config('hotel.cleaning_buffer_minutes', 0);
        $at = fn (CarbonInterface $moment) => $moment->format('d/m/Y à H\hi');

        if ($conflict->status === ReservationStatus::Enregistree && $conflict->check_out->lessThanOrEqualTo(now())) {
            return "La chambre N°{$room} est encore occupée : le départ du séjour en cours était prévu le "
                .$at($conflict->check_out).' et le check-out n\'a pas été fait. Faites le check-out ou prolongez ce séjour d\'abord.';
        }

        // No real overlap: only the cleaning time between the two stays is missing.
        if ($start && $end && $buffer > 0) {
            if ($conflict->check_out->lessThanOrEqualTo($start)) {
                return "La chambre N°{$room} est libérée le ".$at($conflict->check_out)." ; avec le ménage obligatoire de {$buffer} min, le séjour suivant peut commencer au plus tôt le "
                    .$at($conflict->check_out->copy()->addMinutes($buffer)).'.';
            }
            if ($conflict->check_in->greaterThanOrEqualTo($end)) {
                return "La chambre N°{$room} est prise à partir du ".$at($conflict->check_in)." ; avec le ménage obligatoire de {$buffer} min, ce séjour doit se terminer au plus tard le "
                    .$at($conflict->check_in->copy()->subMinutes($buffer)).'.';
            }
        }

        return "La chambre N°{$room} est déjà prise du ".$at($conflict->check_in)
            .' au '.$at($conflict->check_out)
            .($conflict->status === ReservationStatus::Enregistree ? ' (séjour en cours).' : ' (réservation soldée).');
    }

    /**
     * Locks the room row until the end of the current transaction, then re-checks availability:
     * two simultaneous requests for the same room are serialised and the second one sees the
     * first one's stay. Must be called inside DB::transaction(), right before writing.
     *
     * @throws ValidationException
     */
    public static function lockAvailable(int $roomId, CarbonInterface $start, CarbonInterface $end, ?int $excludeReservationId, string $errorKey, ?int $excludeRequestId = null): self
    {
        $room = static::query()->lockForUpdate()->findOrFail($roomId);

        if ($error = $room->availabilityError($start, $end, $excludeReservationId, $excludeRequestId)) {
            throw ValidationException::withMessages([$errorKey => $error]);
        }

        return $room;
    }
}
