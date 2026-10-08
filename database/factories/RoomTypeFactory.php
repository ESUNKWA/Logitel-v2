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
            'hourly_rate' => fake()->randomElement([5000, 7500, 10000]),
            'day_rate' => fake()->randomElement([20000, 25000, 30000]),
            'night_rate' => fake()->randomElement([75000, 95000, 115000]),
            'stay_rate' => fake()->randomElement([90000, 120000, 150000]),
            'capacity_adults' => fake()->numberBetween(1, 4),
            'capacity_children' => fake()->numberBetween(0, 2),
            'is_active' => true,
        ];
    }
}
