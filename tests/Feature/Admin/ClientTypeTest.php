<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clients are natural persons or companies; when booking, a new client needs only a phone.
 */
class ClientTypeTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $this->room = Room::factory()->create(['number' => 'CT-1', 'room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);
        $this->staff = User::factory()->create();
    }

    private function book(array $client)
    {
        return $this->actingAs($this->staff)->post('/admin/reservations', [
            'room_id' => $this->room->id, 'pricing_mode' => 'nuitee', 'check_in' => '2026-10-12', 'duration' => 1,
            'adults' => 1, 'children' => 0, 'status' => 'confirmee', ...$client,
        ]);
    }

    public function test_a_reservation_with_a_new_client_needs_only_a_phone(): void
    {
        $this->book(['new_phone' => '07 11 22 33 44'])->assertSessionHasNoErrors();

        $guest = Reservation::sole()->guest;
        $this->assertSame('07 11 22 33 44', $guest->phone);
        $this->assertSame(Guest::TYPE_PERSON, $guest->type);
        $this->assertSame('Client sans nom', $guest->fullName());
        $this->assertSame('', $guest->first_name);
    }

    public function test_a_reservation_for_a_company(): void
    {
        $this->book([
            'new_client_type' => 'morale', 'new_company_name' => 'SARL Transports du Centre', 'new_tax_id' => 'CI-BKE-2026-B-77',
            'new_first_name' => 'Koffi', 'new_last_name' => 'Yao', 'new_phone' => '0707070707',
        ])->assertSessionHasNoErrors();

        $booking = Reservation::sole();
        $guest = $booking->guest;
        $this->assertTrue($guest->isCompany());
        $this->assertSame('SARL Transports du Centre', $guest->fullName());
        $this->assertSame('Koffi Yao', $guest->contactName());
        $this->assertSame('CI-BKE-2026-B-77', $guest->tax_id);

        $this->actingAs($this->staff)->get('/admin/reservations')->assertSee('SARL Transports du Centre');
        $this->actingAs($this->staff)->get("/admin/recus/{$booking->id}")->assertOk()
            // No client part on the ticket (client request, 2026-10-19).
            ->assertDontSee('SARL Transports du Centre')->assertDontSee('RCCM / NCC')->assertDontSee('CI-BKE-2026-B-77')->assertDontSee('Koffi Yao');
    }

    public function test_company_fields_are_ignored_for_a_person(): void
    {
        $this->book(['new_client_type' => 'physique', 'new_company_name' => 'Ne pas garder', 'new_tax_id' => 'X', 'new_first_name' => 'Awa', 'new_phone' => '0102'])->assertSessionHasNoErrors();

        $guest = Reservation::sole()->guest;
        $this->assertFalse($guest->isCompany());
        $this->assertNull($guest->company_name);
        $this->assertNull($guest->tax_id);
        $this->assertSame('Awa', $guest->fullName());
    }

    public function test_an_existing_client_is_used_and_no_phone_is_needed(): void
    {
        $guest = Guest::factory()->create();
        $this->book(['guest_id' => $guest->id])->assertSessionHasNoErrors();

        $this->assertSame($guest->id, Reservation::sole()->guest_id);
        $this->assertSame(1, Guest::count());
    }

    public function test_the_client_of_a_booking_can_be_changed_to_a_new_one(): void
    {
        $this->book(['guest_id' => Guest::factory()->create()->id]);
        $booking = Reservation::sole();

        $this->actingAs($this->staff)->get("/admin/reservations/{$booking->id}/edit")->assertOk()
            ->assertSee('value="'.$booking->guest_id.'"', false)->assertSee($booking->guest->fullName());

        $this->actingAs($this->staff)->put("/admin/reservations/{$booking->id}", [
            'room_id' => $this->room->id, 'pricing_mode' => 'nuitee', 'check_in' => '2026-10-12', 'duration' => 1,
            'adults' => 1, 'children' => 0, 'status' => 'confirmee', 'guest_id' => '', 'new_phone' => '0505050505',
            'new_client_type' => 'morale', 'new_company_name' => 'ONG Espoir',
        ])->assertSessionHasNoErrors();

        $this->assertSame('ONG Espoir', $booking->fresh()->guest->fullName());
    }

    public function test_the_reservation_form_offers_the_client_picker(): void
    {
        $this->actingAs($this->staff)->get('/admin/reservations/create')->assertOk()
            ->assertSee('data-guest-search', false)->assertSee('name="new_phone"', false)
            ->assertSee('name="new_client_type"', false)->assertSee('Entreprise')->assertDontSee('<select class="form-select" id="guest_id"', false);
    }

    public function test_the_client_form_handles_both_types_and_only_requires_the_phone(): void
    {
        $this->actingAs($this->staff)->get('/admin/guests/create')->assertOk()->assertSee('Entreprise (personne morale)', false)->assertSee('name="company_name"', false);

        $this->actingAs($this->staff)->post('/admin/guests', ['phone' => '0101010101'])->assertSessionHasNoErrors();
        $this->assertSame('Client sans nom', Guest::sole()->fullName());

        $this->actingAs($this->staff)->post('/admin/guests', ['first_name' => 'Awa'])->assertSessionHasErrors('phone');
        $this->actingAs($this->staff)->post('/admin/guests', ['phone' => '02', 'type' => 'association'])->assertSessionHasErrors('type');

        $this->actingAs($this->staff)->post('/admin/guests', ['type' => 'morale', 'company_name' => 'Mairie de Bouaké', 'tax_id' => 'NCC-1', 'phone' => '0303'])->assertSessionHasNoErrors();
        $company = Guest::where('company_name', 'Mairie de Bouaké')->sole();
        $this->assertTrue($company->isCompany());

        // Turning it into a person drops the company fields.
        $this->actingAs($this->staff)->put("/admin/guests/{$company->id}", ['type' => 'physique', 'company_name' => 'Mairie de Bouaké', 'first_name' => 'Jean', 'phone' => '0303'])->assertSessionHasNoErrors();
        $company->refresh();
        $this->assertFalse($company->isCompany());
        $this->assertNull($company->company_name);
        $this->assertSame('Jean', $company->fullName());
    }

    public function test_companies_are_found_by_their_name_everywhere(): void
    {
        $company = Guest::factory()->create(['type' => 'morale', 'company_name' => 'Orange Côte d\'Ivoire', 'first_name' => '', 'last_name' => '', 'phone' => '0909']);
        $stay = Reservation::factory()->create(['guest_id' => $company->id, 'room_id' => $this->room->id, 'check_in' => '2026-10-08 09:00', 'duration' => 2, 'pricing_mode' => 'heure', 'unit_rate' => 5000, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_AFFECTATION]);
        $stay->update(['status' => 'enregistree']);

        $this->actingAs($this->staff)->getJson('/admin/guests/search?q=orange')->assertOk()->assertJsonPath('0.id', $company->id)
            ->assertJsonPath('0.label', fn ($label) => str_contains($label, "Orange Côte d'Ivoire"));
        $this->actingAs($this->staff)->get('/admin/guests?q=Orange')->assertOk()->assertSee('Orange Côte d&#039;Ivoire', false)->assertSee('fa-building', false);
        $this->actingAs($this->staff)->get('/admin/affectations?q=orange')->assertOk()->assertSee($stay->reference());
        $this->actingAs($this->staff)->get('/admin/recherche?q=orange')->assertOk()->assertSee('Orange Côte d&#039;Ivoire', false);
    }
}
