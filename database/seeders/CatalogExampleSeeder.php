<?php

namespace Database\Seeders;

use App\Enums\Department;
use App\Models\CatalogItem;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * Each catalogue only holds what its service sells (client request, 2026-11-27): food in
 * Restauration, drinks in Bar (client request, 2026-11-28); beach & pool = access, rentals, activities; meetings & events = room
 * equipment and event services (an event's catering is a Restauration sale).
 *
 * Example catalogue of the other services (restaurant, beach & pool, meetings & events) and two
 * example venues, to try the sales and room bookings. Safe to run again: an item (or venue) that
 * already exists under the same name is left as it is — its price is never overwritten.
 * Run: php artisan db:seed --class=CatalogExampleSeeder
 * The services still have to be ticked in Configuration → Hôtel → Services proposés.
 */
class CatalogExampleSeeder extends Seeder
{
    /** service => [category, name, price (FCFA), counts as one entry (pool)] */
    private const ITEMS = [
        'restauration' => [
            ['Entrées', 'Salade composée', 2500, false],
            ['Entrées', 'Alloco sauce piment', 1500, false],
            ['Plats', 'Attiéké poisson braisé', 5000, false],
            ['Plats', 'Poulet braisé, attiéké ou alloco', 6000, false],
            ['Plats', 'Kedjenou de poulet', 6500, false],
            ['Plats', 'Foutou sauce graine', 5500, false],
            ['Plats', 'Garba', 2000, false],
            ['Plats', 'Brochettes de bœuf', 4000, false],
            ['Desserts', 'Salade de fruits', 1500, false],
            ['Desserts', 'Dêguê', 1000, false],
            ['Petit-déjeuner', 'Petit-déjeuner continental', 3500, false],
            ['Petit-déjeuner', 'Petit-déjeuner ivoirien', 4000, false],
        ],
        // Drinks: the bar's own catalogue (client request, 2026-11-28 — food in Restauration).
        'bar' => [
            ['Eaux et jus', 'Eau minérale 1,5 L', 1000, false],
            ['Eaux et jus', 'Eau minérale 50 cl', 500, false],
            ['Eaux et jus', 'Jus de bissap', 1000, false],
            ['Eaux et jus', 'Jus de gingembre (gnamakoudji)', 1000, false],
            ['Sodas', 'Soda (Coca, Fanta, Sprite)', 1000, false],
            ['Bières', 'Bière (Flag, Bock)', 1500, false],
            ['Boissons chaudes', 'Café ou thé', 500, false],
            ['Cocktails', 'Cocktail sans alcool', 2000, false],
        ],
        'plage_piscine' => [
            ['Accès', 'Entrée adulte (journée)', 3000, true],
            ['Accès', 'Entrée enfant (journée)', 1500, true],
            ['Accès', 'Entrée client hébergé', 0, true],
            ['Locations', 'Transat', 1000, false],
            ['Locations', 'Serviette de bain', 500, false],
            ['Locations', 'Parasol', 1000, false],
            ['Locations', 'Bouée et brassards', 500, false],
            ['Activités', 'Cours de natation (1 h)', 5000, false],
            ['Activités', 'Aquagym (séance)', 3000, false],
        ],
        'evenements' => [
            ['Matériel', 'Vidéoprojecteur et écran', 15000, false],
            ['Matériel', 'Sonorisation et micros', 20000, false],
            ['Matériel', 'Paperboard et marqueurs', 5000, false],
            ['Matériel', 'Connexion Wi-Fi dédiée', 10000, false],
            ['Services', 'Installation de la salle sur mesure', 25000, false],
            ['Services', 'Décoration de salle', 50000, false],
            ['Services', 'Hôtesse d\'accueil (journée)', 15000, false],
        ],
    ];

    /** name => [capacity, half-day rate, day rate, description] */
    private const VENUES = [
        'Salle Baobab' => [80, 100000, 180000, 'Grande salle climatisée, disposition en théâtre ou en U.'],
        'Salle Kapok' => [20, 35000, 60000, 'Petite salle de réunion avec table de conférence.'],
    ];

    public function run(): void
    {
        $created = 0;
        foreach (self::ITEMS as $service => $items) {
            $department = Department::from($service);
            foreach ($items as $position => [$category, $name, $price, $countsEntry]) {
                $item = CatalogItem::query()->where('department', $department->value)->where('name', $name)->first();
                if ($item) {
                    continue;
                }
                $item = new CatalogItem(['category' => $category, 'name' => $name, 'price' => $price, 'position' => $position,
                    'counts_entry' => $department->countsEntries() && $countsEntry, 'is_active' => true]);
                $item->forceFill(['department' => $department])->save();
                $created++;
            }
        }

        foreach (self::VENUES as $name => [$capacity, $half, $day, $description]) {
            if (! Venue::query()->where('name', $name)->exists()) {
                Venue::create(['name' => $name, 'capacity' => $capacity, 'half_day_rate' => $half, 'day_rate' => $day, 'description' => $description]);
                $created++;
            }
        }

        $this->command?->info($created.' exemple(s) ajouté(s). Cochez les services dans Configuration → Hôtel → Services proposés pour les voir.');
    }
}
