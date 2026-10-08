<?php

namespace App\Rules;

use App\Enums\PricingMode;
use App\Models\Room;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Validates that the room referenced by the attribute under validation (a room_id) has no
 * overlapping active reservation for the stay described by the rest of the form data:
 * pricing_mode (default nuitée) + check_in + duration, or check_out to infer the duration.
 */
class RoomIsAvailableForDates implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function __construct(protected ?int $excludeReservationId = null) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $mode = PricingMode::tryFrom((string) ($this->data['pricing_mode'] ?? '')) ?? PricingMode::Nuitee;
        $checkIn = $this->data['check_in'] ?? null;
        $checkOut = $this->data['check_out'] ?? null;
        $duration = $mode === PricingMode::Nuitee ? null : (int) ($this->data['duration'] ?? 0);

        if (blank($value) || ! is_string($checkIn) || strtotime($checkIn) === false
            || ($duration === null && (! is_string($checkOut) || strtotime($checkOut) === false))
            || $duration === 0) {
            return;
        }

        [$start, $end] = $mode->resolve(Carbon::parse($checkIn), $duration, $checkOut ? Carbon::parse($checkOut) : null);

        $error = Room::find($value)?->availabilityError($start, $end, $this->excludeReservationId);

        if ($error) {
            $fail($error);
        }
    }
}
