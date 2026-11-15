<?php

namespace Tests\Feature\Admin;

use App\Enums\PricingMode;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The hourly formula is called « Passage » everywhere (client request, 2026-11-15); only its
 * label changed: the stored value stays « heure » and it is still billed per hour.
 */
class PassageLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_hourly_formula_is_labelled_passage_and_still_billed_per_hour(): void
    {
        $this->assertSame('Passage', PricingMode::Heure->getLabel());
        $this->assertSame('heure', PricingMode::Heure->value);
        $this->assertSame('3 heures', PricingMode::Heure->durationLabel(3));
        $this->assertSame('/ heure', PricingMode::Heure->rateUnit());
    }

    public function test_no_screen_says_a_l_heure_any_more(): void
    {
        $this->travelTo('2026-11-15 10:00:00');
        $user = User::factory()->create();
        $type = RoomType::factory()->create(['hourly_rate' => 5000]);
        $room = Room::factory()->create(['room_type_id' => $type->id]);
        $stay = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => PricingMode::Heure, 'duration' => 2, 'unit_rate' => 5000,
            'check_in' => now()->subHour(), 'status' => 'enregistree', 'origin' => Reservation::ORIGIN_AFFECTATION]);

        $pages = ['/admin/rooms/board', '/admin/reservations/create', '/admin/room-types', '/admin/room-types/'.$type->id.'/edit',
            '/admin/affectations', $stay->fileUrl(), route('admin.receipts.show', $stay), '/admin/recettes?formule=heure'];

        foreach ($pages as $page) {
            $html = html_entity_decode((string) $this->actingAs($user)->get($page)->assertOk()->getContent(), ENT_QUOTES);
            $this->assertStringNotContainsStringIgnoringCase("à l'heure", $html, $page);
        }

        $this->actingAs($user)->get('/admin/rooms/board')->assertSee('Passage');
        $this->actingAs($user)->get('/admin/affectations')->assertSee('Passage')->assertSee('2 heures');
        $this->actingAs($user)->get('/admin/room-types/'.$type->id.'/edit')->assertSee('Tarif passage (par heure)');
    }
}
