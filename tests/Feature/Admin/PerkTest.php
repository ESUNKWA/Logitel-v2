<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Perk;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Included perks (Configuration → Avantages inclus, client request 2026-10-25): « Accès piscine
 * gratuit » for the suites in séjour. Snapshotted on affectations and réservations of the matching
 * room type and formula, printed on the receipt, shown at the desk and on the website's room page
 * (séjour only); managed by the Gérant only.
 */
class PerkTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $suite;

    private RoomType $standard;

    private Room $suiteRoom;

    private Room $standardRoom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $this->suite = RoomType::factory()->create(['name' => 'SUITE', 'hourly_rate' => 10000, 'night_rate' => 50000, 'stay_rate' => 60000]);
        $this->standard = RoomType::factory()->create(['name' => 'STANDARD', 'hourly_rate' => 3000, 'night_rate' => 15000, 'stay_rate' => 20000]);
        $this->suiteRoom = Room::factory()->create(['number' => 'S-1', 'room_type_id' => $this->suite->id, 'status' => RoomStatus::Disponible, 'is_published' => true, 'public_name' => 'Suite Lagune']);
        $this->standardRoom = Room::factory()->create(['number' => 'C-1', 'room_type_id' => $this->standard->id, 'status' => RoomStatus::Disponible, 'is_published' => true, 'public_name' => 'Chambre Confort']);
        $this->actingAs(User::factory()->create());
    }

    private function perk(array $attributes = [], ?array $types = null): Perk
    {
        $perk = Perk::create(['label' => 'Accès piscine gratuit', 'all_room_types' => false, 'modes' => ['sejour'], 'is_active' => true, ...$attributes]);
        $perk->roomTypes()->sync($types ?? [$this->suite->id]);

        return $perk;
    }

    private function assign(Room $room, string $mode, int $paid)
    {
        return $this->post("/admin/rooms/{$room->id}/assign", [
            'pricing_mode' => $mode, 'duration' => 1, 'adults' => 2, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => $paid]],
        ]);
    }

    private function book(Room $room, string $mode = 'sejour')
    {
        return $this->post('/admin/reservations', [
            'guest_id' => Guest::factory()->create()->id, 'room_id' => $room->id, 'pricing_mode' => $mode,
            'check_in' => '2026-10-12T14:00', 'duration' => 1, 'adults' => 2, 'children' => 0, 'status' => 'confirmee',
        ]);
    }

    // ---- Which files get it ----------------------------------------------------------------

    public function test_a_suite_affectation_in_sejour_gets_the_perk_on_its_receipt_and_file(): void
    {
        $this->perk();

        $this->assign($this->suiteRoom, 'sejour', 60000)->assertSessionHasNoErrors();

        $stay = Reservation::sole();
        $this->assertSame(['Accès piscine gratuit'], $stay->perks);
        $this->get("/admin/recus/{$stay->id}")->assertOk()
            ->assertSeeInOrder(['SOLDÉ', 'AVANTAGES INCLUS', '• Accès piscine gratuit', 'Le ticket vendu'], false);
        $this->get("/admin/affectations/{$stay->id}")->assertOk()
            ->assertSee('Avantages inclus')->assertSee('Accès piscine gratuit');
    }

    public function test_other_formulas_and_room_types_dont_get_it(): void
    {
        $this->perk();

        $this->assign($this->suiteRoom, 'heure', 10000)->assertSessionHasNoErrors();
        $this->assign($this->standardRoom, 'sejour', 20000)->assertSessionHasNoErrors();

        Reservation::all()->each(fn (Reservation $file) => $this->assertNull($file->perks));
        $this->get('/admin/recus/'.Reservation::first()->id)->assertOk()->assertDontSee('AVANTAGES INCLUS');
    }

    public function test_a_suite_reservation_in_sejour_gets_it_too_and_keeps_it_on_arrival(): void
    {
        $this->perk();

        $this->book($this->suiteRoom)->assertSessionHasNoErrors();
        $booking = Reservation::sole();
        $this->assertSame(['Accès piscine gratuit'], $booking->perks);
        $this->get("/admin/recus/{$booking->id}")->assertOk()->assertSee('AVANTAGES INCLUS')->assertSee('Accès piscine gratuit');

        // Guest arrives (status change only): the snapshot stays.
        $this->travelTo('2026-10-12 14:30:00');
        $this->post("/admin/reservations/{$booking->id}/check-in")->assertSessionHasNoErrors();
        $this->assertSame(['Accès piscine gratuit'], $booking->fresh()->perks);
    }

    public function test_moving_a_booking_to_another_formula_or_room_type_takes_the_perks_again(): void
    {
        $this->perk();
        $this->book($this->suiteRoom)->assertSessionHasNoErrors();
        $booking = Reservation::sole();

        $booking->update(['pricing_mode' => 'heure']);
        $this->assertNull($booking->fresh()->perks);

        $booking->update(['pricing_mode' => 'sejour']);
        $this->assertSame(['Accès piscine gratuit'], $booking->fresh()->perks);

        $booking->update(['room_id' => $this->standardRoom->id]);
        $this->assertNull($booking->fresh()->perks);
    }

    public function test_every_formula_all_types_order_and_inactive_perks(): void
    {
        $this->perk(['label' => 'Parking gratuit', 'modes' => null, 'all_room_types' => true, 'position' => 2], []);
        $this->perk(['label' => 'Petit-déjeuner offert', 'position' => 1]);
        $this->perk(['label' => 'Spa', 'is_active' => false]);

        $this->assign($this->suiteRoom, 'sejour', 60000)->assertSessionHasNoErrors();
        $this->assign($this->standardRoom, 'heure', 3000)->assertSessionHasNoErrors();

        [$suite, $standard] = Reservation::orderBy('id')->get()->all();
        $this->assertSame(['Petit-déjeuner offert', 'Parking gratuit'], $suite->perks); // ordered, Spa inactive
        $this->assertSame(['Parking gratuit'], $standard->perks); // all types, every formula
    }

    public function test_editing_or_deleting_a_perk_never_changes_existing_files(): void
    {
        $perk = $this->perk();
        $this->assign($this->suiteRoom, 'sejour', 60000)->assertSessionHasNoErrors();
        $stay = Reservation::sole();

        $this->put(route('admin.perks.update', $perk), ['label' => 'Piscine + transat', 'room_type_ids' => [$this->suite->id], 'modes' => ['sejour'], 'is_active' => 1])
            ->assertRedirect(route('admin.perks.index'));
        $this->assertSame(['Accès piscine gratuit'], $stay->fresh()->perks);

        $this->delete(route('admin.perks.destroy', $perk))->assertRedirect(route('admin.perks.index'));
        $this->assertModelMissing($perk);
        $this->get("/admin/recus/{$stay->id}")->assertOk()->assertSee('Accès piscine gratuit');

        // An extension (same room and formula) keeps the snapshot.
        $stay->fresh()->update(['duration' => 2]);
        $this->assertSame(['Accès piscine gratuit'], $stay->fresh()->perks);
    }

    // ---- At the desk ---------------------------------------------------------------------

    public function test_the_desk_forms_carry_the_perks_per_formula(): void
    {
        $this->perk();
        $json = e(json_encode(['sejour' => ['Accès piscine gratuit']]));

        $board = $this->get('/admin/rooms/board')->assertOk()->assertSee('data-perks-hint', false)->getContent();
        $this->assertStringContainsString('data-perks="'.$json.'"', $board); // the suite's card
        $this->assertStringContainsString('data-perks="{}"', $board); // the standard room's card

        $this->get('/admin/reservations/create')->assertOk()
            ->assertSee('data-perks="'.$json.'"', false)->assertSee('data-perks-hint', false);
    }

    // ---- Website ---------------------------------------------------------------------------

    public function test_the_room_page_shows_the_sejour_perks_only(): void
    {
        $this->perk();
        $this->perk(['label' => 'Boisson <b>offerte</b>', 'modes' => ['heure']]);

        $this->get($this->suiteRoom->publicUrl())->assertOk()
            ->assertSee('Inclus avec votre séjour')->assertSee('Accès piscine gratuit')
            ->assertDontSee('Boisson');
        $this->get($this->standardRoom->publicUrl())->assertOk()->assertDontSee('Inclus avec votre séjour');
    }

    public function test_perk_labels_are_escaped_on_the_website_and_receipt(): void
    {
        $this->perk(['label' => '<script>alert(1)</script> Piscine']);

        $this->get($this->suiteRoom->publicUrl())->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);

        $this->assign($this->suiteRoom, 'sejour', 60000)->assertSessionHasNoErrors();
        $this->get('/admin/recus/'.Reservation::sole()->id)->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    // ---- Configuration -----------------------------------------------------------------------

    public function test_the_manager_creates_a_perk_for_the_suites_in_sejour(): void
    {
        $this->get(route('admin.perks.index'))->assertOk()->assertSee('Aucun avantage');
        $this->get(route('admin.perks.create'))->assertOk();

        $this->post(route('admin.perks.store'), [
            'label' => '  Accès piscine gratuit ', 'all_room_types' => 0, 'room_type_ids' => [$this->suite->id],
            'modes' => ['sejour'], 'position' => 1, 'is_active' => 1,
        ])->assertRedirect(route('admin.perks.index'));

        $perk = Perk::sole();
        $this->assertSame('Accès piscine gratuit', $perk->label);
        $this->assertSame(['sejour'], $perk->modes);
        $this->assertSame([$this->suite->id], $perk->roomTypes->pluck('id')->all());

        $this->get(route('admin.perks.index'))->assertOk()->assertSee('Accès piscine gratuit')->assertSee('SUITE')->assertSee('Séjour');
        $this->get(route('admin.perks.edit', $perk))->assertOk()->assertSee('value="Accès piscine gratuit"', false);
    }

    public function test_every_or_no_formula_ticked_means_all_formulas(): void
    {
        foreach ([[], ['heure', 'journee', 'nuitee', 'sejour']] as $modes) {
            $this->post(route('admin.perks.store'), ['label' => 'Wifi', 'all_room_types' => 1, 'modes' => $modes, 'is_active' => 1])->assertSessionHasNoErrors();
        }

        Perk::all()->each(function (Perk $perk) {
            $this->assertNull($perk->modes);
            $this->assertSame('Toutes les formules', $perk->modesLabel());
        });
    }

    public function test_perks_are_validated(): void
    {
        $this->post(route('admin.perks.store'), ['label' => '', 'room_type_ids' => []])
            ->assertSessionHasErrors(['label', 'room_type_ids']);
        $this->post(route('admin.perks.store'), ['label' => str_repeat('a', 121), 'all_room_types' => 1])
            ->assertSessionHasErrors('label');
        $this->post(route('admin.perks.store'), ['label' => 'X', 'all_room_types' => 1, 'modes' => ['gratuit']])
            ->assertSessionHasErrors('modes.0');
        $this->post(route('admin.perks.store'), ['label' => 'X', 'room_type_ids' => [999]])
            ->assertSessionHasErrors('room_type_ids.0');
        $this->assertSame(0, Perk::count());
    }

    public function test_only_the_manager_configures_perks(): void
    {
        $perk = $this->perk();
        $reception = User::factory()->reception()->create();

        $this->actingAs($reception)->get(route('admin.perks.index'))->assertForbidden();
        $this->actingAs($reception)->post(route('admin.perks.store'), ['label' => 'X', 'all_room_types' => 1])->assertForbidden();
        $this->actingAs($reception)->delete(route('admin.perks.destroy', $perk))->assertForbidden();
        $this->actingAs($reception)->get('/admin/rooms')->assertOk()->assertDontSee(route('admin.perks.index'), false);

        foreach ([User::factory()->create(), User::factory()->simpleUi()->create()] as $manager) {
            $this->actingAs($manager)->get('/admin/rooms')->assertOk()->assertSee(route('admin.perks.index'), false)->assertSee('Avantages inclus');
        }
    }
}
