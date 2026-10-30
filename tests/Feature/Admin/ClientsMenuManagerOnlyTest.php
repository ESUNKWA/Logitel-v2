<?php

namespace Tests\Feature\Admin;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The « Clients » menu and list are the Gérant's only (client request, 2026-10-25), through the
 * guests.list permission. The Réception keeps finding clients where it works (réservation form,
 * front-desk search) and editing a client's file from there.
 */
class ClientsMenuManagerOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manager_sees_the_clients_menu_and_list_in_both_layouts(): void
    {
        foreach ([User::factory()->create(), User::factory()->simpleUi()->create()] as $manager) {
            $this->actingAs($manager)->get('/admin/rooms')->assertOk()
                ->assertSee('href="'.route('admin.guests.index').'"', false)
                ->assertSee('Clients');
            $this->actingAs($manager)->get('/admin/guests')->assertOk();
        }
    }

    public function test_the_reception_has_no_clients_menu_nor_list(): void
    {
        foreach ([User::factory()->reception()->create(), User::factory()->reception()->simpleUi()->create()] as $reception) {
            $this->actingAs($reception)->get('/admin/rooms')->assertOk()
                ->assertDontSee('href="'.route('admin.guests.index').'"', false);
            $this->actingAs($reception)->get('/admin/guests')->assertForbidden();
        }
    }

    public function test_the_reception_still_finds_and_edits_clients_where_it_works(): void
    {
        $reception = User::factory()->reception()->create();
        $guest = Guest::factory()->create(['last_name' => 'Kouassi', 'phone' => '0701020304']);

        $this->actingAs($reception)->getJson('/admin/guests/search?q=Kouassi')->assertOk()->assertJsonPath('0.id', $guest->id);
        $this->actingAs($reception)->get('/admin/recherche?q=Kouassi')->assertOk()->assertSee(route('admin.guests.edit', $guest), false);
        $this->actingAs($reception)->get('/admin/reservations/create')->assertOk()->assertSee('data-guest-search', false);

        // Editing stays on the client's file (no 403 on a list it can't open), Annuler → the search.
        $this->actingAs($reception)->get(route('admin.guests.edit', $guest))->assertOk()
            ->assertSee('href="'.route('admin.front-desk.search').'"', false)
            ->assertDontSee('href="'.route('admin.guests.index').'"', false);
        $this->actingAs($reception)->put(route('admin.guests.update', $guest), [
            'type' => 'physique', 'first_name' => 'Awa', 'last_name' => 'Kouassi', 'phone' => '0701020304',
        ])->assertRedirect(route('admin.guests.edit', $guest));
        $this->assertSame('Awa', $guest->fresh()->first_name);

        $this->actingAs($reception)->post(route('admin.guests.store'), [
            'type' => 'physique', 'first_name' => 'Yao', 'last_name' => 'Konan', 'phone' => '0505050505',
        ])->assertRedirect(route('admin.guests.edit', Guest::where('first_name', 'Yao')->sole()));
    }

    public function test_the_manager_goes_back_to_the_list_after_saving(): void
    {
        $guest = Guest::factory()->create();

        $this->actingAs(User::factory()->create())->put(route('admin.guests.update', $guest), [
            'type' => 'physique', 'first_name' => 'Awa', 'last_name' => 'Kouassi', 'phone' => '0701020304',
        ])->assertRedirect(route('admin.guests.index'));
    }
}
