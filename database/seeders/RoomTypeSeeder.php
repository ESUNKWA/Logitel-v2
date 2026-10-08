<?php

namespace Database\Seeders;

use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Chambre Deluxe',
                'slug' => 'chambre-deluxe',
                'description' => "Idéale pour les voyageurs seuls ou en couple, à la recherche d'un confort élégant.",
                'night_rate' => 75000,
                'capacity_adults' => 2,
                'capacity_children' => 1,
            ],
            [
                'name' => 'Suite Signature',
                'slug' => 'suite-signature',
                'description' => 'Une expérience haut de gamme avec des équipements de luxe et plus d\'espace.',
                'night_rate' => 115000,
                'capacity_adults' => 2,
                'capacity_children' => 2,
            ],
            [
                'name' => 'Chambre Familiale',
                'slug' => 'chambre-familiale',
                'description' => 'Spacieuse et confortable, pensée pour les séjours en famille.',
                'night_rate' => 135000,
                'capacity_adults' => 4,
                'capacity_children' => 2,
            ],
        ];

        foreach ($types as $type) {
            RoomType::query()->updateOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
