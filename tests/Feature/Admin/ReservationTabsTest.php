<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Réservations list tabs (client request, 2026-11-10): « À affecter » (default, nearest arrival
 * first, « Affecter » straight from the line), « Déjà affectées », « Annulées ».
 */
class ReservationTabsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 10:00:00');
        $this->staff = User::factory()->create();
    }

    private function booking(string $number, string $checkIn, ReservationStatus $status = ReservationStatus::Confirmee, RoomStatus $roomStatus = RoomStatus::Disponible): Reservation
    {
        $room = Room::factory()->create(['number' => $number, 'room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);
        $booking = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 1000, 'duration' => 2,
            'check_in' => $checkIn, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        if ($status !== ReservationStatus::Confirmee) {
            $booking->update(['status' => $status]);
        }
        $room->refresh()->update(['status' => $roomStatus]);

        return $booking->refresh();
    }

    public function test_bookings_to_assign_are_listed_apart_and_by_default(): void
    {
        $later = $this->booking('LATER', '2026-10-20 14:00');
        $today = $this->booking('TODAY', '2026-10-14 15:00');
        $pending = $this->booking('PEND', '2026-10-16 15:00', ReservationStatus::EnAttente);
        $arrived = $this->booking('ARRIVED', '2026-10-14 09:00', ReservationStatus::Enregistree);
        $cancelled = $this->booking('CANCEL', '2026-10-15 09:00', ReservationStatus::Annulee);

        // Default tab: to assign, nearest arrival first, counts on every tab.
        $this->actingAs($this->staff)->get(route('admin.reservations.index'))->assertOk()
            ->assertSeeInOrder(['À affecter', '3', 'Déjà affectées', '1', 'Annulées', '1'])
            ->assertSeeInOrder(['class="nav-link active"', 'aria-current="page"', 'À affecter', 'class="nav-link "', 'Déjà affectées'], false)
            ->assertSeeInOrder(['N°TODAY', 'N°PEND', 'N°LATER'])
            ->assertDontSee('N°ARRIVED')->assertDontSee('N°CANCEL')
            ->assertSee('aria-label="Statut"', false);

        $this->get(route('admin.reservations.index', ['onglet' => 'affectees']))->assertOk()
            ->assertSee('N°ARRIVED')->assertSee($arrived->reference())->assertSee('Voir l\'affectation', false)
            ->assertDontSee('N°TODAY')->assertDontSee('N°CANCEL')
            ->assertDontSee('aria-label="Statut"', false);

        $this->get(route('admin.reservations.index', ['onglet' => 'annulees']))->assertOk()
            ->assertSee('N°CANCEL')->assertDontSee('N°TODAY')->assertDontSee('N°ARRIVED');

        // Status filter inside « À affecter »; the room filter keeps the tab and scopes the counts.
        $this->get(route('admin.reservations.index', ['status' => 'en_attente']))->assertOk()
            ->assertSee('N°PEND')->assertDontSee('N°TODAY')->assertDontSee('N°LATER');
        $this->get(route('admin.reservations.index', ['onglet' => 'a-affecter', 'room' => 'LATER']))->assertOk()
            ->assertSee('N°LATER')->assertDontSee('N°TODAY')
            ->assertSeeInOrder(['À affecter', '1', 'Déjà affectées', '0', 'Annulées', '0'])
            ->assertSee('onglet=affectees&amp;room=LATER', false);
    }

    public function test_older_links_and_garbage_values(): void
    {
        $this->booking('TODAY', '2026-10-14 15:00');
        $this->booking('ARRIVED', '2026-10-14 09:00', ReservationStatus::Enregistree);
        $this->booking('CANCEL', '2026-10-15 09:00', ReservationStatus::Annulee);

        $this->actingAs($this->staff)->get('/admin/reservations?status=honoree')->assertOk()->assertSee('N°ARRIVED')->assertDontSee('N°TODAY');
        $this->get('/admin/reservations?status=annulee')->assertOk()->assertSee('N°CANCEL')->assertDontSee('N°TODAY');
        $this->get('/admin/reservations?onglet=historique')->assertOk()->assertSee('N°TODAY');
        $this->get('/admin/reservations?onglet[]=affectees&status[]=x&room[]=1')->assertOk()->assertSee('N°TODAY');
        // A status of another tab doesn't empty « Déjà affectées ».
        $this->get('/admin/reservations?onglet=affectees&status=en_attente')->assertOk()->assertSee('N°ARRIVED');
    }

    public function test_assign_button_only_when_the_guest_can_arrive(): void
    {
        $today = $this->booking('TODAY', '2026-10-14 15:00');
        $tomorrow = $this->booking('TOMORROW', '2026-10-15 15:00');
        $busy = $this->booking('BUSY', '2026-10-14 16:00', roomStatus: RoomStatus::Nettoyage);
        $over = $this->booking('OVER', '2026-10-14 06:00'); // 06:00 → 08:00, already over
        $checkIn = fn (Reservation $r) => 'action="'.route('admin.reservations.check-in', $r).'"';

        $page = $this->actingAs($this->staff)->get(route('admin.reservations.index'))->assertOk();
        $page->assertSee($checkIn($today), false)->assertSee('Oui, affecter la chambre');
        foreach ([$tomorrow, $busy, $over] as $booking) {
            $page->assertDontSee($checkIn($booking), false);
        }

        // Same action as « Arrivée du client »: the booking becomes an affectation.
        $this->post(route('admin.reservations.check-in', $today))->assertRedirect(route('admin.affectations.show', $today));
        $this->assertSame(ReservationStatus::Enregistree, $today->refresh()->status);
        $this->get(route('admin.reservations.index'))->assertDontSee('N°TODAY');
        $this->get(route('admin.reservations.index', ['onglet' => 'affectees']))->assertSee('N°TODAY');
    }

    public function test_no_assign_button_without_reservations_manage(): void
    {
        $today = $this->booking('TODAY', '2026-10-14 15:00');
        $role = Role::create(['name' => 'Lecture']);
        $role->syncPermissions(['reservations.view']);
        $viewer = User::factory()->create();
        $viewer->forceFill(['role_id' => $role->id])->save();

        $this->actingAs($viewer)->get(route('admin.reservations.index'))->assertOk()
            ->assertSee('N°TODAY')->assertDontSee(route('admin.reservations.check-in', $today), false)
            ->assertDontSee(route('admin.reservations.cancel', $today), false);
    }

    public function test_cancel_button_on_bookings_to_assign(): void
    {
        $later = $this->booking('LATER', '2026-10-20 14:00'); // no « Affecter » yet, « Annuler » already
        $arrived = $this->booking('ARRIVED', '2026-10-14 09:00', ReservationStatus::Enregistree);
        $cancel = fn (Reservation $r) => 'action="'.route('admin.reservations.cancel', $r).'"';

        $this->actingAs($this->staff)->get(route('admin.reservations.index'))->assertOk()
            ->assertSee($cancel($later), false)->assertSee('Oui, annuler la réservation')
            ->assertSee('data-confirm-danger', false);
        $this->get(route('admin.reservations.index', ['onglet' => 'affectees']))->assertOk()
            ->assertDontSee($cancel($arrived), false);

        // Same action as on the booking's page: back to the list, the booking moves to « Annulées ».
        $this->from(route('admin.reservations.index'))->post(route('admin.reservations.cancel', $later))
            ->assertRedirect(route('admin.reservations.index'))->assertSessionHas('status', 'Réservation annulée.');
        $this->assertSame(ReservationStatus::Annulee, $later->refresh()->status);
        $this->get(route('admin.reservations.index'))->assertDontSee('N°LATER');
        $this->get(route('admin.reservations.index', ['onglet' => 'annulees']))->assertSee('N°LATER')->assertDontSee($cancel($later), false);
    }

    public function test_no_cancel_button_without_reservations_cancel(): void
    {
        $later = $this->booking('LATER', '2026-10-20 14:00');
        $role = Role::create(['name' => 'Sans annulation']);
        $role->syncPermissions(['reservations.view', 'reservations.manage']);
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $role->id])->save();

        $this->actingAs($user)->get(route('admin.reservations.index'))->assertOk()
            ->assertSee(route('admin.reservations.edit', $later), false)
            ->assertDontSee(route('admin.reservations.cancel', $later), false);
        $this->post(route('admin.reservations.cancel', $later))->assertForbidden();
    }
}
