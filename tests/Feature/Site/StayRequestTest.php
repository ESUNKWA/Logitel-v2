<?php

namespace Tests\Feature\Site;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\StayRequestStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StayRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * A guest who has just arrived picks a free room from their phone (/demande) and sends a
 * request; it holds the room until the réception validates it (affectation, paid in full) or
 * refuses it, or until it expires.
 */
class StayRequestTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $type = RoomType::factory()->create(['name' => 'Confort', 'hourly_rate' => 5000, 'day_rate' => 15000, 'night_rate' => 25000, 'stay_rate' => null, 'is_active' => true]);
        $this->room = Room::factory()->create(['number' => 'DM-1', 'room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
    }

    private function sendRequest(array $extra = [], ?Room $room = null)
    {
        return $this->post('/demande/chambre/'.($room ?? $this->room)->id, [
            'pricing_mode' => 'heure', 'duration' => 3,
            'first_name' => 'Awa', 'last_name' => 'Koné', 'phone' => '07 11 22 33 44',
            'adults' => 1, 'children' => 0, 'website' => '',
            ...$extra,
        ]);
    }

    private function approve(StayRequest $request, array $extra = [], ?User $user = null)
    {
        return $this->actingAs($user ?? User::factory()->create())->post("/admin/demandes/{$request->id}/valider", [
            'pricing_mode' => 'heure', 'duration' => 3, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'especes', 'amount' => 15000, 'received' => 20000]],
            ...$extra,
        ]);
    }

    public function test_public_list_shows_only_rooms_free_now_with_their_open_formulas(): void
    {
        $type = $this->room->roomType;
        Room::factory()->create(['number' => 'DM-OCC', 'room_type_id' => $type->id, 'status' => RoomStatus::Occupee]);
        Room::factory()->create(['number' => 'DM-HS', 'room_type_id' => $type->id, 'status' => RoomStatus::HorsService]);
        Room::factory()->create(['number' => 'DM-NET', 'room_type_id' => $type->id, 'status' => RoomStatus::Nettoyage]);
        $inactive = RoomType::factory()->create(['hourly_rate' => 1000, 'is_active' => false]);
        Room::factory()->create(['number' => 'DM-OFF', 'room_type_id' => $inactive->id, 'status' => RoomStatus::Disponible]);

        $this->travelTo('2026-10-08 14:00:00');
        $this->get('/demande')->assertOk()
            ->assertSee('N°DM-1')
            ->assertDontSee('N°DM-OCC')->assertDontSee('N°DM-HS')->assertDontSee('N°DM-NET')->assertDontSee('N°DM-OFF')
            ->assertSee("À l'heure")
            ->assertDontSee('Nuitée'); // 14:00: the nuitée window (22:00 → 12:00) is closed

        $this->get('/demande/chambre/'.$this->room->id)->assertOk()->assertSee('Envoyer ma demande');
    }

    public function test_a_request_holds_the_room_and_hides_it_from_other_guests(): void
    {
        $response = $this->sendRequest()->assertSessionHasNoErrors();

        $request = StayRequest::sole();
        $this->assertSame(StayRequestStatus::EnAttente, $request->status);
        $this->assertSame('2026-10-08 10:00', $request->check_in->format('Y-m-d H:i'));
        $this->assertSame('2026-10-08 13:00', $request->check_out->format('Y-m-d H:i'));
        $this->assertSame('15000.00', (string) $request->total_amount);
        $this->assertSame('2026-10-08 10:30', $request->expires_at->format('Y-m-d H:i'));

        // The guest lands on their (signed) follow-up page.
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Demande envoyée')->assertSee('N° '.$request->id);

        // Gone from the public list, and nobody else can ask for it.
        $this->get('/demande')->assertOk()->assertDontSee('N°DM-1');
        $this->get('/demande/chambre/'.$this->room->id)->assertRedirect('/demande');
        $this->sendRequest(['phone' => '05 99 99 99 99'])->assertSessionHasErrors('duration');
        $this->assertSame(1, StayRequest::count());
    }

    public function test_the_follow_up_page_needs_the_signed_link(): void
    {
        $this->sendRequest();
        $request = StayRequest::sole();

        $this->get('/demande/suivi/'.$request->id)->assertForbidden();
        $this->get(URL::signedRoute('stay-request.show', $request))->assertOk();
    }

    public function test_a_pending_request_blocks_the_desk_too_until_it_expires(): void
    {
        $this->sendRequest();
        $staff = User::factory()->create();

        $this->actingAs($staff)->get('/admin/rooms/board')->assertOk()->assertSee('Demande client')->assertSee('Traiter la demande');
        $this->assertFalse(Room::query()->availableNow()->whereKey($this->room->id)->exists());

        // Direct assignment from the board is refused…
        $this->actingAs($staff)->post("/admin/rooms/{$this->room->id}/assign", [
            'new_first_name' => 'Jean', 'new_last_name' => 'Yao', 'new_phone' => '0101010101',
            'pricing_mode' => 'heure', 'duration' => 1, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 5000]],
        ])->assertSessionHasErrors('duration');

        // …and so is a reservation overlapping the held period.
        $this->actingAs($staff)->post('/admin/reservations', [
            'guest_id' => Guest::factory()->create()->id, 'room_id' => $this->room->id, 'pricing_mode' => 'heure',
            'check_in' => '2026-10-08T12:00', 'duration' => 2, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0, 'status' => 'confirmee',
        ])->assertSessionHasErrors('room_id');
        $this->assertSame(0, Reservation::count());

        // Once expired, the room is free again for everyone.
        $this->travel(31)->minutes();
        $this->assertTrue(Room::query()->availableNow()->whereKey($this->room->id)->exists());
        $this->get('/demande')->assertSee('N°DM-1');
    }

    public function test_validating_creates_the_guest_and_the_paid_affectation(): void
    {
        $this->sendRequest();
        $request = StayRequest::sole();
        $staff = User::factory()->create();

        $this->travel(5)->minutes(); // the guest walks to the desk
        $this->actingAs($staff)->get("/admin/demandes/{$request->id}")->assertOk()->assertSee("Valider l'affectation et encaisser", false);
        $this->approve($request, user: $staff)->assertSessionHasNoErrors()->assertRedirect();

        $stay = Reservation::sole();
        $this->assertSame(ReservationStatus::Enregistree, $stay->status);
        $this->assertSame(Reservation::ORIGIN_AFFECTATION, $stay->origin);
        $this->assertSame('2026-10-08 10:05', $stay->check_in->format('Y-m-d H:i')); // starts when validated
        $this->assertSame('2026-10-08 13:05', $stay->check_out->format('Y-m-d H:i'));
        $this->assertSame('Awa Koné', $stay->guest->fullName());
        $this->assertSame('07 11 22 33 44', $stay->guest->phone);
        $this->assertSame(InvoiceStatus::Payee, $stay->invoice->status);
        $this->assertSame('5000.00', (string) $stay->invoice->payments()->sole()->change_given);
        $this->assertSame(RoomStatus::Occupee, $this->room->fresh()->status);

        $request->refresh();
        $this->assertSame(StayRequestStatus::Validee, $request->status);
        $this->assertSame($stay->id, $request->reservation_id);
        $this->assertSame($staff->id, $request->handled_by);

        // The guest's follow-up page says the room is ready; the affectation links back to the request.
        $this->get(URL::signedRoute('stay-request.show', $request))->assertSee('Votre chambre est prête');
        $this->actingAs($staff)->get("/admin/affectations/{$stay->id}")->assertOk()->assertSee('demande client N°'.$request->id);
    }

    public function test_validating_requires_full_payment_and_the_right_to_cash(): void
    {
        $this->sendRequest();
        $request = StayRequest::sole();

        $this->approve($request, ['payments' => [['method' => 'wave', 'amount' => 5000]]])->assertSessionHasErrors('payments');
        $this->approve($request, ['payments' => []])->assertSessionHasErrors('payments');

        $role = Role::create(['name' => 'Stagiaire']);
        $role->syncPermissions([Permission::AffectationsView->value, Permission::AffectationsManage->value]);
        $intern = User::factory()->create(['role_id' => $role->id]);
        $this->approve($request, user: $intern)->assertForbidden();
        $this->actingAs($intern)->get("/admin/demandes/{$request->id}")->assertOk()->assertSee("droit d'enregistrer un paiement", false);

        $this->assertSame(0, Reservation::count());
        $this->assertTrue($request->fresh()->isPending());
    }

    public function test_staff_can_adjust_the_formula_when_validating(): void
    {
        $this->sendRequest();
        $request = StayRequest::sole();

        $this->approve($request, [
            'pricing_mode' => 'journee', 'duration' => 1,
            'discount_type' => 'montant', 'discount_value' => 3000,
            'payments' => [['method' => 'orange_money', 'amount' => 12000]],
        ])->assertSessionHasNoErrors();

        $stay = Reservation::sole();
        $this->assertSame('journee', $stay->pricing_mode->value);
        $this->assertSame('15000.00', (string) $stay->unit_rate); // the room type's day rate
        $this->assertSame('12000.00', (string) $stay->total_amount); // − 3 000 discount
    }

    public function test_an_existing_client_is_recognised_by_phone_unless_told_otherwise(): void
    {
        $known = Guest::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné', 'phone' => '0711223344']);

        $this->sendRequest();
        $first = StayRequest::sole();
        $this->actingAs(User::factory()->create())->get("/admin/demandes/{$first->id}")->assertSee('Client déjà connu');
        $this->approve($first)->assertSessionHasNoErrors();
        $this->assertSame($known->id, Reservation::sole()->guest_id);
        $this->assertSame(1, Guest::count());

        $other = Room::factory()->create(['number' => 'DM-2', 'room_type_id' => $this->room->room_type_id, 'status' => RoomStatus::Disponible]);
        $this->sendRequest(room: $other)->assertSessionHasNoErrors();
        $second = StayRequest::where('room_id', $other->id)->sole();
        $this->approve($second, ['new_guest' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2, Guest::count());
    }

    public function test_a_request_is_handled_only_once(): void
    {
        $this->sendRequest();
        $request = StayRequest::sole();

        $this->approve($request)->assertSessionHasNoErrors();
        $this->approve($request)->assertSessionHasErrors('pricing_mode');
        $this->actingAs(User::factory()->create())->post("/admin/demandes/{$request->id}/refuser")->assertSessionHasErrors('refusal_reason');

        $this->assertSame(1, Reservation::count());
        $this->assertSame(StayRequestStatus::Validee, $request->fresh()->status);
    }

    public function test_refusing_frees_the_room_and_tells_the_guest(): void
    {
        $this->sendRequest();
        $request = StayRequest::sole();
        $staff = User::factory()->create();

        $this->actingAs($staff)->post("/admin/demandes/{$request->id}/refuser", ['refusal_reason' => 'Client absent'])
            ->assertRedirect('/admin/demandes');

        $request->refresh();
        $this->assertSame(StayRequestStatus::Refusee, $request->status);
        $this->assertSame($staff->id, $request->handled_by);
        $this->assertTrue(Room::query()->availableNow()->whereKey($this->room->id)->exists());
        $this->get(URL::signedRoute('stay-request.show', $request))->assertSee('Demande non retenue')->assertSee('Client absent');
    }

    public function test_an_expired_request_can_still_be_validated_if_the_room_is_free(): void
    {
        $this->sendRequest();
        $request = StayRequest::sole();

        $this->travel(40)->minutes();
        $this->get(URL::signedRoute('stay-request.show', $request))->assertSee('Délai dépassé');
        $this->actingAs(User::factory()->create())->get('/admin/demandes?onglet=expirees')->assertOk()->assertSee('Awa Koné');

        $this->approve($request)->assertSessionHasNoErrors();
        $this->assertSame(1, Reservation::count());
    }

    public function test_invalid_or_abusive_requests_are_refused(): void
    {
        $this->sendRequest(['website' => 'http://spam.example'])->assertSessionHasErrors('website');
        $this->sendRequest(['pricing_mode' => 'sejour'])->assertSessionHasErrors('pricing_mode'); // no rate
        $this->sendRequest(['phone' => 'abc'])->assertSessionHasErrors('phone');
        $this->sendRequest(['duration' => 0])->assertSessionHasErrors('duration');
        $this->sendRequest(['unit_rate' => 1, 'total_amount' => 1])->assertSessionHasNoErrors();
        $this->assertSame('15000.00', (string) StayRequest::sole()->total_amount); // price never taken from input

        // One pending request per phone number.
        $other = Room::factory()->create(['number' => 'DM-3', 'room_type_id' => $this->room->room_type_id, 'status' => RoomStatus::Disponible]);
        $this->sendRequest(['phone' => '0711223344'], $other)->assertSessionHasErrors('phone');
        $this->assertSame(1, StayRequest::count());
    }

    public function test_the_nuitee_cannot_be_requested_outside_its_window(): void
    {
        $this->travelTo('2026-10-08 14:00:00');

        $this->sendRequest(['pricing_mode' => 'nuitee', 'duration' => 1])->assertSessionHasErrors('pricing_mode');
        $this->assertSame(0, StayRequest::count());
    }

    public function test_the_nuitee_can_be_requested_at_night(): void
    {
        $this->travelTo('2026-10-08 23:10:00');

        $this->get('/demande')->assertSee('Nuitée');
        $this->sendRequest(['pricing_mode' => 'nuitee', 'duration' => 1])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-09 12:00', StayRequest::sole()->check_out->format('Y-m-d H:i'));
    }

    public function test_staff_are_notified_and_see_the_requests(): void
    {
        $this->sendRequest();
        $request = StayRequest::sole();
        $reception = User::factory()->reception()->create();

        $this->actingAs($reception)->get('/admin/demandes/flux')->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $request->id)
            ->assertJsonPath('0.room', 'DM-1')
            ->assertJsonPath('0.guest', 'Awa Koné');

        $this->actingAs($reception)->get('/admin')->assertOk()->assertSee('Demandes clients')->assertSee('id="requestAlerts"', false);
        $this->actingAs($reception)->get('/admin/demandes')->assertOk()->assertSee('Awa Koné')->assertSee('Traiter');
        $this->actingAs($reception)->get('/admin/rooms?status=demande')->assertOk()->assertSee('Demande client');

        // Expired requests leave the feed.
        $this->travel(31)->minutes();
        $this->actingAs($reception)->get('/admin/demandes/flux')->assertJsonCount(0);
    }

    public function test_request_pages_are_staff_only_and_permission_guarded(): void
    {
        $this->sendRequest();
        $request = StayRequest::sole();

        $this->get('/admin/demandes')->assertRedirect(route('admin.login'));
        $this->get('/admin/demandes/flux')->assertRedirect(route('admin.login'));

        $noRole = User::factory()->withoutRole()->create();
        $this->actingAs($noRole)->get('/admin/demandes')->assertForbidden();
        $this->actingAs($noRole)->get("/admin/demandes/{$request->id}")->assertForbidden();
        $this->actingAs($noRole)->post("/admin/demandes/{$request->id}/refuser")->assertForbidden();
        $this->assertTrue($request->fresh()->isPending());
    }

    public function test_the_manager_sets_how_long_a_request_holds_the_room(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->get('/admin/parametres')->assertOk()->assertSee('request_expiry_minutes');
        $this->actingAs($manager)->put('/admin/parametres', [
            'business_day_start' => '06:00', 'night_from' => '22:00', 'night_until' => '12:00', 'day_hours' => 6,
            'stay_min_hours' => 24, 'cleaning_buffer_minutes' => 30, 'request_expiry_minutes' => 15,
        ])->assertSessionHasNoErrors();

        $this->sendRequest();
        $this->assertSame('2026-10-08 10:15', StayRequest::sole()->expires_at->format('Y-m-d H:i'));
    }

    public function test_name_and_phone_are_optional_for_the_guest(): void
    {
        $this->get('/demande/chambre/'.$this->room->id)->assertOk()
            ->assertSee('Prénom <span class="dm-optional">(facultatif)</span>', false)
            ->assertDontSee('autocomplete="tel" placeholder="07 00 00 00 00" maxlength="30" required', false);

        $response = $this->sendRequest(['first_name' => '', 'last_name' => '', 'phone' => ''])->assertSessionHasNoErrors();

        $request = StayRequest::sole();
        $this->assertSame('', $request->phone);
        $this->assertSame('Client de passage', $request->fullName());
        $this->assertSame(StayRequestStatus::EnAttente, $request->status);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Client de passage');

        // The réception sees it, and validating it creates a "Client de passage" file.
        $staff = User::factory()->create();
        $this->actingAs($staff)->get('/admin/demandes')->assertSee('Client de passage');
        $this->actingAs($staff)->get("/admin/demandes/{$request->id}")->assertOk()->assertSee('Nouveau client');
        $this->approve($request, user: $staff)->assertSessionHasNoErrors();

        $guest = Reservation::sole()->guest;
        $this->assertSame('Client de passage', $guest->fullName());
        $this->assertSame('', $guest->phone);
    }

    public function test_guests_without_phone_are_not_blocked_by_the_one_request_per_phone_rule(): void
    {
        $this->sendRequest(['phone' => ''])->assertSessionHasNoErrors();

        $other = Room::factory()->create(['number' => 'DM-9', 'room_type_id' => $this->room->room_type_id, 'status' => RoomStatus::Disponible]);
        $this->sendRequest(['phone' => '', 'first_name' => 'Jean'], $other)->assertSessionHasNoErrors();

        $this->assertSame(2, StayRequest::count());
    }

    public function test_a_phone_given_must_still_be_valid(): void
    {
        $this->sendRequest(['phone' => 'abc'])->assertSessionHasErrors('phone');
        $this->sendRequest(['first_name' => str_repeat('a', 101)])->assertSessionHasErrors('first_name');
        $this->assertSame(0, StayRequest::count());
    }
}
