<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Customer receipt (thermal ticket) after an affectation / a réservation: preview, content,
 * reprint, settings (roll width, header, logo) and access.
 */
class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private array $settings = [
        'business_day_start' => '06:00', 'night_from' => '22:00', 'night_until' => '12:00', 'day_hours' => 6,
        'stay_min_hours' => 24, 'cleaning_buffer_minutes' => 30,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $type = RoomType::factory()->create(['name' => 'Confort', 'hourly_rate' => 5000, 'night_rate' => 25000]);
        $this->room = Room::factory()->create(['number' => 'RC-1', 'room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
    }

    private function assign(User $user)
    {
        return $this->actingAs($user)->post("/admin/rooms/{$this->room->id}/assign", [
            'new_first_name' => 'Awa', 'new_last_name' => 'Koné', 'new_phone' => '0711223344',
            'pricing_mode' => 'heure', 'duration' => 3, 'unit_rate' => 5000, 'adults' => 2, 'children' => 0,
            'payments' => [['method' => 'especes', 'amount' => 10000, 'received' => 12000], ['method' => 'wave', 'amount' => 5000]],
        ]);
    }

    public function test_the_preview_opens_right_after_an_affectation_with_the_full_ticket(): void
    {
        $user = User::factory()->create(['name' => 'Aminata Réception']);
        $this->assign($user)->assertSessionHas('receipt');
        $stay = Reservation::sole();

        $this->actingAs($user)->get('/admin/rooms/board')->assertOk()
            ->assertSee('id="receiptModal"', false)
            ->assertSee(route('admin.receipts.show', ['reservation' => $stay->id, 'apercu' => 1]), false);
        // Only once: the next page doesn't reopen it.
        $this->actingAs($user)->get('/admin/rooms/board')->assertDontSee('id="receiptModal"', false);

        $this->actingAs($user)->get("/admin/recus/{$stay->id}")->assertOk()
            ->assertDontSee('REÇU')->assertDontSee('class="title"', false) // no "REÇU / Affectation" heading (client request)
            ->assertDontSee('RÉSERVATION</div>', false)->assertDontSee('Tél. client') // an affectation's ticket: no title, no client phone
            ->assertSee($stay->invoice->number)
            ->assertDontSee('Awa Koné')->assertDontSee('0711223344')->assertDontSee('<span>Client</span>', false)->assertDontSee('Tél.') // no client part (client request, 2026-10-19)
            ->assertSee('N°RC-1')
            ->assertDontSee('Confort')->assertDontSee('<span>Type</span>', false)->assertDontSee('Personnes')->assertDontSee(' ad.') // neither room type nor guests (client request, 2026-10-19)
            ->assertSee('3 heures × 5 000')
            ->assertSee('15 000 FCFA')
            // Payment part: received (12 000 cash + 5 000 Wave), change (2 000) and paid (client
            // request, 2026-10-25) — still no method (2026-10-19).
            ->assertSeeInOrder([
                '<div class="row"><span>Montant reçu</span><span>17 000 FCFA</span></div>',
                '<div class="row"><span>Monnaie rendue</span><span>2 000 FCFA</span></div>',
                '<div class="row strong"><span>Montant payé</span><span>15 000 FCFA</span></div>',
            ], false)
            ->assertDontSee('Espèces')->assertDontSee('Wave')->assertDontSee('Paiements')->assertDontSee('<span>Reçu</span>', false)
            ->assertSee('SOLDÉ')
            ->assertDontSee('Établi par')->assertDontSee('Aminata Réception') // no staff name on the ticket (client request)
            ->assertSee('Imprimer')
            ->assertSee('@page { margin: 0; }', false)->assertDontSee('size: 80mm', false)->assertSee('max-width: 80mm', false);
    }

    public function test_a_reservation_receipt_shows_the_deposit_and_what_is_left_to_pay(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/admin/reservations', [
            'guest_id' => Guest::factory()->create(['first_name' => 'Jean', 'last_name' => 'Yao', 'phone' => '0102030405'])->id,
            'room_id' => $this->room->id, 'pricing_mode' => 'heure', 'check_in' => '2026-10-10T14:00', 'duration' => 2,
            'unit_rate' => 5000, 'adults' => 1, 'children' => 0, 'status' => 'confirmee',
            'payments' => [['method' => 'orange_money', 'amount' => 4000]],
        ])->assertSessionHasNoErrors()->assertSessionHas('receipt');
        $booking = Reservation::sole();

        $this->actingAs($user)->get('/admin/reservations')->assertSee('id="receiptModal"', false);
        $this->actingAs($user)->get("/admin/recus/{$booking->id}")->assertOk()
            // « RÉSERVATION » on top and the client's phone (client request, 2026-11-10); still no name.
            ->assertSeeInOrder(['<div class="doc-title">RÉSERVATION</div>', 'Reçu N°', '<div class="row"><span>Tél. client</span><span class="strong">0102030405</span></div>', 'N°RC-1'], false)
            ->assertDontSee('Jean Yao')
            ->assertSee('Arrivée prévue')->assertSee('10/10/2026 14h00')
            ->assertSee('<span>Montant payé</span><span>4 000 FCFA</span>', false)->assertDontSee('Orange Money')
            // Mobile money: received = the amount, no change.
            ->assertSee('<span>Montant reçu</span><span>4 000 FCFA</span>', false)
            ->assertSee('<span>Monnaie rendue</span><span>0 FCFA</span>', false)
            ->assertSee("À PAYER À L'ARRIVÉE")->assertSee('6 000 FCFA')
            ->assertDontSee('SOLDÉ');
    }

    public function test_the_reservation_title_and_phone_only_while_it_is_a_booking(): void
    {
        $user = User::factory()->create();
        $booking = Reservation::factory()->create(['guest_id' => Guest::factory()->create(['phone' => '0505050505'])->id, 'room_id' => $this->room->id,
            'pricing_mode' => 'heure', 'unit_rate' => 5000, 'duration' => 2, 'check_in' => '2026-10-10 14:00', 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        $noPhone = Reservation::factory()->create(['guest_id' => Guest::factory()->create(['phone' => ''])->id, 'room_id' => Room::factory()->create(['room_type_id' => $this->room->room_type_id])->id,
            'pricing_mode' => 'heure', 'unit_rate' => 5000, 'duration' => 2, 'check_in' => '2026-10-11 14:00', 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);

        $this->actingAs($user)->get("/admin/recus/{$noPhone->id}")->assertOk()
            ->assertSee('<div class="doc-title">RÉSERVATION</div>', false)->assertDontSee('Tél. client');

        // Once the guest has arrived the file is an affectation: its ticket has neither.
        $this->travelTo('2026-10-10 14:05:00');
        $booking->update(['status' => ReservationStatus::Enregistree]);
        $this->get("/admin/recus/{$booking->id}")->assertOk()
            ->assertDontSee('RÉSERVATION</div>', false)->assertDontSee('Tél. client')->assertDontSee('0505050505');
    }

    public function test_the_receipt_can_be_reprinted_from_the_files(): void
    {
        $user = User::factory()->create();
        $this->assign($user);
        $stay = Reservation::sole();
        $url = route('admin.receipts.show', $stay);

        $this->actingAs($user)->get("/admin/affectations/{$stay->id}")->assertSee($url, false)->assertSee('Imprimer le reçu');
        $this->actingAs($user)->get("/admin/invoices/{$stay->invoice->id}")->assertSee($url, false);

        $booking = Reservation::factory()->create(['room_id' => $this->room->id, 'check_in' => '2026-10-12 14:00', 'check_out' => '2026-10-13 12:00', 'origin' => Reservation::ORIGIN_RESERVATION, 'status' => 'confirmee']);
        $this->actingAs($user)->get("/admin/reservations/{$booking->id}/edit")->assertSee(route('admin.receipts.show', $booking), false);
    }

    public function test_the_preview_embedded_in_the_modal_has_no_toolbar(): void
    {
        $user = User::factory()->create();
        $this->assign($user);
        $stay = Reservation::sole();

        $this->actingAs($user)->get("/admin/recus/{$stay->id}?apercu=1")->assertOk()
            ->assertDontSee('class="toolbar"', false)->assertSee('Reçu N°');
    }

    public function test_received_and_change_lines_only_with_payments_and_for_older_cash_payments(): void
    {
        $user = User::factory()->create();
        $booking = Reservation::factory()->create(['room_id' => $this->room->id]);

        // No payment yet: no payment part at all.
        $this->actingAs($user)->get("/admin/recus/{$booking->id}")->assertOk()
            ->assertDontSee('Montant reçu')->assertDontSee('Monnaie rendue')->assertDontSee('Montant payé');

        // A cash payment recorded without « received » (older rows): received = amount, change 0.
        $booking->invoice->payments()->create(['method' => 'especes', 'amount' => 3000, 'paid_at' => now()]);
        $this->actingAs($user)->get("/admin/recus/{$booking->id}")->assertOk()
            ->assertSee('<span>Montant reçu</span><span>3 000 FCFA</span>', false)
            ->assertSee('<span>Monnaie rendue</span><span>0 FCFA</span>', false)
            ->assertSee('<span>Montant payé</span><span>3 000 FCFA</span>', false);
    }

    public function test_a_cancelled_file_is_stamped_cancelled(): void
    {
        $user = User::factory()->create();
        $this->assign($user);
        $stay = Reservation::sole();
        $this->actingAs($user)->post("/admin/affectations/{$stay->id}/cancel");

        $this->actingAs($user)->get("/admin/recus/{$stay->id}")->assertSee('ANNULÉ')->assertDontSee('SOLDÉ');
    }

    public function test_every_ticket_says_it_is_neither_exchanged_nor_refunded(): void
    {
        // Client request, 2026-10-19: fixed notice, after the footer message, on every ticket.
        $user = User::factory()->create();
        $this->assign($user);
        $stay = Reservation::sole();

        $this->actingAs($user)->get("/admin/recus/{$stay->id}")->assertOk()
            ->assertSeeInOrder(['SOLDÉ', 'Merci de votre visite', 'Le ticket vendu ne sera ni échangé ni remboursé', 'Imprimé le']);

        // Even with an empty footer message, and on a cancelled file.
        $this->actingAs($user)->put('/admin/parametres', [...$this->settings, 'receipt_footer' => ''])->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/admin/affectations/{$stay->id}/cancel");
        $this->actingAs($user)->get("/admin/recus/{$stay->id}")->assertOk()
            ->assertDontSee('Merci de votre visite')
            ->assertSeeInOrder(['ANNULÉ', 'Le ticket vendu ne sera ni échangé ni remboursé', 'Imprimé le']);
    }

    public function test_the_manager_sets_the_roll_width_and_footer(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->get('/admin/parametres')->assertOk()->assertSee('Largeur du rouleau')->assertSee(route('admin.hotel.edit'), false);
        $this->actingAs($manager)->put('/admin/parametres', [...$this->settings, 'receipt_width' => 58, 'receipt_footer' => 'Au plaisir de vous revoir'])
            ->assertSessionHasNoErrors();

        $this->assign($manager);
        $this->actingAs($manager)->get('/admin/recus/'.Reservation::sole()->id)->assertOk()
            ->assertSee('max-width: 58mm', false)
            ->assertSee('Au plaisir de vous revoir');
    }

    public function test_receipt_settings_are_validated(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->put('/admin/parametres', [...$this->settings, 'receipt_width' => 70])->assertSessionHasErrors('receipt_width');
        $this->actingAs($manager)->put('/admin/parametres', [...$this->settings, 'receipt_footer' => str_repeat('x', 201)])->assertSessionHasErrors('receipt_footer');
    }

    public function test_receipts_need_the_invoice_permission(): void
    {
        $this->assign(User::factory()->create());
        $stay = Reservation::sole();
        $this->app['auth']->forgetGuards();

        $this->get("/admin/recus/{$stay->id}")->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->withoutRole()->create())->get("/admin/recus/{$stay->id}")->assertForbidden();
        $this->actingAs(User::factory()->reception()->create())->get("/admin/recus/{$stay->id}")->assertOk();
    }
}
