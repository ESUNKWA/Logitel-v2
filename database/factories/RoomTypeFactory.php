<?php

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Chambre Deluxe', 'Suite Signature', 'Chambre Familiale']);

        return [
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 100000),
            'description' => fake()->sentence(),
            // Fixed rates: the billed rate is always the room type's (not editable), so tests
            // must know it — random rates made some of them pass or fail by chance.
            'hourly_rate' => 5000,
            'day_rate' => 20000,
            'night_rate' => 75000,
            'stay_rate' => 90000,
            'capacity_adults' => fake()->numberBetween(1, 4),
            'capacity_children' => fake()->numberBetween(0, 2),
            'is_active' => true,
        ];
    }
}
