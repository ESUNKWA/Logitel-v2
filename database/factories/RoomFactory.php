<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'number' => (string) fake()->unique()->numberBetween(100, 999),
            'floor' => (string) fake()->numberBetween(1, 5),
            'status' => RoomStatus::Disponible,
        ];
    }
}
