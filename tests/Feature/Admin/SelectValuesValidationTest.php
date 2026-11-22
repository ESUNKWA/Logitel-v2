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
 * A value posted for a <select> must be one the list offers — the browser list is no protection,
 * the server decides (OWASP A04).
 */
class SelectValuesValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $this->staff = User::factory()->create();
    }

    private function book(array $data)
    {
        $room = Room::factory()->create(['number' => 'SV-1', 'room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);

        return [$room, $this->actingAs($this->staff)->post('/admin/reservations', [
            'room_id' => $room->id, 'pricing_mode' => 'nuitee', 'check_in' => '2026-10-12', 'duration' => 1,
            'adults' => 1, 'children' => 0, 'status' => 'confirmee', 'new_phone' => '0711223344', ...$data,
        ])];
    }

    public function test_a_reservation_refuses_an_unknown_room_type(): void
    {
        [, $response] = $this->book(['room_type_id' => 999999]);

        $response->assertSessionHasErrors('room_type_id');
        $this->assertSame(0, Reservation::count());
    }

    public function test_a_reservation_accepts_the_room_s_own_type(): void
    {
        $room = Room::factory()->create(['number' => 'SV-2', 'room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);

        $this->actingAs($this->staff)->post('/admin/reservations', [
            'room_type_id' => $room->room_type_id, 'room_id' => $room->id, 'pricing_mode' => 'nuitee', 'check_in' => '2026-10-12',
            'duration' => 1, 'adults' => 1, 'children' => 0, 'status' => 'confirmee', 'new_phone' => '0711223344',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Reservation::count());
    }

    public function test_a_guest_document_type_must_be_one_of_the_list(): void
    {
        $this->actingAs($this->staff)->post('/admin/guests', ['phone' => '0700000001', 'id_document_type' => '<script>alert(1)</script>'])
            ->assertSessionHasErrors('id_document_type');
        $this->assertSame(0, Guest::count());

        $this->actingAs($this->staff)->post('/admin/guests', ['phone' => '0700000002', 'id_document_type' => 'passeport'])
            ->assertSessionHasNoErrors();
        $this->assertSame('passeport', Guest::sole()->id_document_type);
    }

    public function test_an_older_free_text_document_type_does_not_block_editing_the_guest(): void
    {
        $guest = Guest::factory()->create(['phone' => '0700000003', 'id_document_type' => 'Carte consulaire']);

        // The form offers the guest's current value, so saving the file keeps it.
        $this->actingAs($this->staff)->get(route('admin.guests.edit', $guest))->assertOk()
            ->assertSee('<option value="Carte consulaire" selected>Carte consulaire</option>', false);

        $this->actingAs($this->staff)->put(route('admin.guests.update', $guest), ['phone' => '0700000003', 'id_document_type' => 'Carte consulaire', 'notes' => 'ok'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Carte consulaire', $guest->fresh()->id_document_type);

        // …but another unknown value is refused, and a listed one can replace it.
        $this->actingAs($this->staff)->put(route('admin.guests.update', $guest), ['phone' => '0700000003', 'id_document_type' => 'Autre chose'])
            ->assertSessionHasErrors('id_document_type');
        $this->actingAs($this->staff)->put(route('admin.guests.update', $guest), ['phone' => '0700000003', 'id_document_type' => 'cni'])
            ->assertSessionHasNoErrors();
        $this->assertSame('cni', $guest->fresh()->id_document_type);
    }

    public function test_a_new_guest_form_has_no_extra_option(): void
    {
        $this->actingAs($this->staff)->get(route('admin.guests.create'))->assertOk()
            ->assertSee("Carte Nationale d'Identité")
            ->assertDontSee('Carte consulaire');
    }
}
