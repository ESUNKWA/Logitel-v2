<?php

namespace App\Models;

use App\Enums\PricingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A booking request sent from the website (« Réserver », /reservation). Not a reservation: it
 * creates no invoice and holds no room. The réception calls the guest back, then turns it into a
 * réservation through the usual form (pre-filled, see ReservationController@create) — which
 * marks it « traitée » in the same transaction — or refuses it.
 */
class BookingRequest extends Model
{
    public const STATUS_PENDING = 'en_attente';

    public const STATUS_HANDLED = 'traitee';

    public const STATUS_REFUSED = 'refusee';

    /** [label, Bootstrap color] per status. */
    public const STATUSES = [
        self::STATUS_PENDING => ['En attente', 'warning'],
        self::STATUS_HANDLED => ['Réservation créée', 'success'],
        self::STATUS_REFUSED => ['Refusée', 'secondary'],
    ];

    protected $fillable = [
        'room_type_id', 'pricing_mode', 'check_in', 'duration', 'adults', 'children',
        'unit_rate', 'first_name', 'last_name', 'phone', 'email', 'message',
    ];

    protected function casts(): array
    {
        return [
            'pricing_mode' => PricingMode::class,
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'handled_at' => 'datetime',
            'duration' => 'integer',
            'adults' => 'integer',
            'children' => 'integer',
            'unit_rate' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Like Reservation: the departure and the (indicative) total are always derived.
        static::saving(function (BookingRequest $request) {
            $request->check_out = $request->pricing_mode->end($request->check_in, max(1, (int) $request->duration));
            $request->total_amount = bcmul((string) $request->unit_rate, (string) max(1, (int) $request->duration), 2);
            $request->status ??= self::STATUS_PENDING;
        });
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: 'Nom non précisé';
    }

    public function billingLabel(): string
    {
        return $this->pricing_mode->durationLabel($this->duration).' × '.number_format((float) $this->unit_rate, 0, ',', ' ').' FCFA';
    }

    /**
     * The existing client with the same phone number, if any (same rule as guest requests).
     */
    public function matchingGuest(): ?Guest
    {
        $phone = StayRequest::normalizePhone($this->phone);

        return $phone === '' ? null : Guest::query()
            ->whereRaw("replace(replace(replace(replace(phone, ' ', ''), '.', ''), '-', ''), '/', '') = ?", [$phone])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Room types offered on the website's booking form: active, with at least one published
     * room and at least one rate.
     *
     * @return Collection<int, RoomType>
     */
    public static function offeredRoomTypes(): Collection
    {
        return RoomType::query()
            ->where('is_active', true)
            ->whereHas('rooms', fn (Builder $q) => $q->where('is_published', true))
            ->orderBy('name')
            ->get()
            ->filter(fn (RoomType $type) => collect(PricingMode::cases())->contains(fn (PricingMode $m) => $m->rateFor($type) !== null))
            ->values();
    }
}
