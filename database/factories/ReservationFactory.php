<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'room_id' => Room::factory(),
            'pricing_mode' => PricingMode::Nuitee,
            // Date-only nuitée: starts at 22:00, check_out is derived from the duration by the model.
            'check_in' => fake()->dateTimeBetween('+1 day', '+10 days')->format('Y-m-d'),
            // When a test passes an explicit check_out, let the model infer the duration from it.
            'duration' => fn (array $attributes) => isset($attributes['check_out']) ? null : fake()->numberBetween(1, 5),
            'adults' => fake()->numberBetween(1, 3),
            'children' => 0,
            'status' => ReservationStatus::EnAttente,
            'unit_rate' => fake()->randomElement([75000, 95000, 115000]),
        ];
    }

    /**
     * A booking paid in full — i.e. guaranteed, holding its room (a real payment on its
     * invoice, so `is_guaranteed` is computed the normal way).
     */
    public function guaranteed(): static
    {
        return $this->afterCreating(function (Reservation $reservation) {
            if ((float) $reservation->total_amount > 0) {
                $reservation->invoice->settle([['method' => PaymentMethod::Wave, 'amount' => $reservation->total_amount]], now());
            }
            $reservation->refreshGuarantee();
        });
    }
}
