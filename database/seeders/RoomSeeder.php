<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $deluxe = RoomType::query()->where('slug', 'chambre-deluxe')->first();
        $suite = RoomType::query()->where('slug', 'suite-signature')->first();
        $familiale = RoomType::query()->where('slug', 'chambre-familiale')->first();

        if (! $deluxe || ! $suite || ! $familiale) {
            return;
        }

        $rooms = [
            ['number' => '101', 'floor' => '1', 'room_type_id' => $deluxe->id],
            ['number' => '102', 'floor' => '1', 'room_type_id' => $deluxe->id],
            ['number' => '103', 'floor' => '1', 'room_type_id' => $deluxe->id],
            ['number' => '201', 'floor' => '2', 'room_type_id' => $suite->id],
            ['number' => '202', 'floor' => '2', 'room_type_id' => $suite->id],
            ['number' => '301', 'floor' => '3', 'room_type_id' => $familiale->id],
            ['number' => '302', 'floor' => '3', 'room_type_id' => $familiale->id],
        ];

        foreach ($rooms as $room) {
            Room::query()->updateOrCreate(['number' => $room['number']], $room);
        }
    }
}
