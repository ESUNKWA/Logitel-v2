<?php

namespace App\Models;

use App\Enums\PricingMode;
use App\Enums\StayRequestStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A guest's room request ("demande"), submitted from their phone on arrival (public page
 * /demande). While pending and not expired it holds the room like a stay (see
 * constrainToConflicts(), used by Room's availability checks), so the room disappears from the
 * public list and can't be given to anyone else. The réception then validates it into an
 * affectation (paid in full, see App\Support\RoomAssignment) or refuses it.
 */
class StayRequest extends Model
{
    protected $fillable = [
        'room_id',
        'first_name',
        'last_name',
        'phone',
        'adults',
        'children',
        'pricing_mode',
        'duration',
        'unit_rate',
        'check_in',
    ];

    protected function casts(): array
    {
        return [
            'pricing_mode' => PricingMode::class,
            'status' => StayRequestStatus::class,
            'duration' => 'integer',
            'adults' => 'integer',
            'children' => 'integer',
            'unit_rate' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'expires_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Like Reservation: the departure and the total are always derived, never submitted.
        static::saving(function (StayRequest $request) {
            if ($request->pricing_mode && $request->check_in) {
                $request->check_out = $request->pricing_mode->end($request->check_in, max(1, (int) $request->duration));
            }
            $request->total_amount = bcmul((string) $request->unit_rate, (string) max(1, (int) $request->duration), 2);
            $request->status ??= StayRequestStatus::EnAttente;
            $request->expires_at ??= now()->addMinutes((int) config('hotel.request_expiry_minutes', 30));
        });
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Pending and not expired: these hold their room and ring at the réception.
     */
    public function scopeHolding(Builder $query): Builder
    {
        return $query->where('status', StayRequestStatus::EnAttente)->where('expires_at', '>', now());
    }

    /**
     * Pending but past their expiry: no longer hold the room, can still be validated if it's free.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', StayRequestStatus::EnAttente)->where('expires_at', '<=', now());
    }

    /**
     * Holding requests overlapping [start, end), widened by the cleaning buffer on both sides
     * (same rule as Room::constrainToConflicts() for stays).
     */
    public static function constrainToConflicts(Builder $requests, CarbonInterface $start, CarbonInterface $end, ?int $excludeRequestId = null): Builder
    {
        $buffer = (int) config('hotel.cleaning_buffer_minutes', 0);

        return $requests
            ->where('stay_requests.status', StayRequestStatus::EnAttente)
            ->where('stay_requests.expires_at', '>', now())
            ->where('stay_requests.check_in', '<', Carbon::instance($end)->addMinutes($buffer))
            ->where('stay_requests.check_out', '>', Carbon::instance($start)->subMinutes($buffer))
            ->when($excludeRequestId, fn (Builder $q, int $id) => $q->where('stay_requests.id', '!=', $id));
    }

    public function isPending(): bool
    {
        return $this->status === StayRequestStatus::EnAttente;
    }

    public function isExpired(): bool
    {
        return $this->isPending() && $this->expires_at->lessThanOrEqualTo(now());
    }

    /**
     * [label, Bootstrap color], "Expirée" included.
     *
     * @return array{0: string, 1: string}
     */
    public function displayStatus(): array
    {
        return $this->isExpired() ? ['Expirée', 'secondary'] : [$this->status->getLabel(), $this->status->getColor()];
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * "3 heures × 5 000 FCFA".
     */
    public function billingLabel(): string
    {
        return $this->pricing_mode->durationLabel($this->duration).' × '.number_format((float) $this->unit_rate, 0, ',', ' ').' FCFA';
    }

    /**
     * Phone digits only (and a leading +), to match an existing guest whatever the spacing.
     */
    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/(?!^\+)[^0-9]/', '', trim($phone)) ?? '';
    }

    /**
     * The existing client with the same phone number, if any.
     */
    public function matchingGuest(): ?Guest
    {
        $phone = self::normalizePhone($this->phone);

        if ($phone === '') {
            return null;
        }

        return Guest::query()
            ->whereRaw("replace(replace(replace(replace(phone, ' ', ''), '.', ''), '-', ''), '/', '') = ?", [$phone])
            ->orderByDesc('id')
            ->first();
    }
}
