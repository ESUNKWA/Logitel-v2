<?php

namespace Tests\Feature\Site;

use App\Console\Commands\CheckWebsiteFormulas;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `php artisan site:check-formulas`: lists the published texts that still mention another formula
 * than the séjour (staff to rephrase); read only; unpublished content ignored.
 */
class CheckWebsiteFormulasTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_texts_mentioning_another_formula(): void
    {
        foreach (['Tarif nuitée avantageux', "Disponible à l'heure", 'A l’heure aussi', '5 000 F / heure', 'Forfait journée', 'NUITEE'] as $text) {
            $this->assertTrue(CheckWebsiteFormulas::mentions($text), $text);
        }
        foreach (['Une belle nuit de repos', 'Séjour de 24 h', 'Horaires d\'ouverture : 8h-20h', 'Petit-déjeuner à 7 heures', 'Belle journées'] as $text) {
            $this->assertFalse(CheckWebsiteFormulas::mentions($text), $text);
        }

        $type = RoomType::factory()->create();
        Room::factory()->create(['room_type_id' => $type->id, 'number' => '101', 'is_published' => true, 'description' => 'Idéale pour une nuitée.']);
        Room::factory()->create(['room_type_id' => $type->id, 'number' => '102', 'is_published' => false, 'description' => 'Nuitée (brouillon).']);
        Service::create(['title' => 'Piscine', 'description' => "Accès à l'heure", 'is_published' => true]);
        SiteContent::query()->create(['key' => 'hero_text', 'value' => 'Bienvenue, séjours de 24 h.']);

        $this->artisan('site:check-formulas')
            ->expectsOutputToContain('2 texte(s) à reformuler')
            ->expectsTable(['Où', 'Élément', 'Champ', 'Extrait'], [
                ['Chambre', 'N°101', 'description', 'Idéale pour une nuitée.'],
                ['Service', 'Piscine', 'description', "Accès à l'heure"],
            ])
            ->assertSuccessful();
        $this->assertSame('Idéale pour une nuitée.', Room::where('number', '101')->value('description')); // read only
    }

    public function test_nothing_to_report(): void
    {
        $this->artisan('site:check-formulas')->expectsOutput('Aucun texte publié ne mentionne une autre formule que le séjour.')->assertSuccessful();
    }
}
