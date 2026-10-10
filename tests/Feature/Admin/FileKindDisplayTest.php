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
 * Affectations and réservations share one table but are never mixed up on screen: badge,
 * colour, icon and reference (AFF-… / RES-…) everywhere a file is shown.
 */
class FileKindDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $this->staff = User::factory()->create();
        $this->guest = Guest::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné']);
    }

    private function room(string $number): Room
    {
        return Room::factory()->create(['number' => $number, 'room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);
    }

    private function stay(): Reservation
    {
        $stay = Reservation::factory()->create(['guest_id' => $this->guest->id, 'room_id' => $this->room('101')->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000,
            'check_in' => '2026-10-08 09:00', 'duration' => 3, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_AFFECTATION]);
        $stay->update(['status' => ReservationStatus::Enregistree]);

        return $stay;
    }

    private function booking(string $room = '201'): Reservation
    {
        return Reservation::factory()->create(['guest_id' => $this->guest->id, 'room_id' => $this->room($room)->id, 'pricing_mode' => 'nuitee', 'unit_rate' => 75000,
            'check_in' => '2026-10-12', 'duration' => 1, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
    }

    public function test_references_and_kinds(): void
    {
        $stay = $this->stay();
        $booking = $this->booking();

        $this->assertSame('affectation', $stay->kind());
        $this->assertSame('Affectation', $stay->kindLabel());
        $this->assertSame('AFF-'.str_pad((string) $stay->id, 6, '0', STR_PAD_LEFT), $stay->reference());
        $this->assertNull($stay->bookingReference());

        $this->assertSame('reservation', $booking->kind());
        $this->assertSame('RES-'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT), $booking->reference());
        $this->assertSame(route('admin.reservations.edit', $booking), $booking->fileUrl());

        // The guest arrives: the réservation becomes an affectation, keeping a link to RES-.
        $this->travelTo('2026-10-12 22:30:00');
        $booking->update(['status' => ReservationStatus::Enregistree]);
        $this->assertSame('affectation', $booking->kind());
        $this->assertStringStartsWith('AFF-', $booking->reference());
        $this->assertStringStartsWith('RES-', $booking->bookingReference());
        $this->assertSame(route('admin.affectations.show', $booking), $booking->fileUrl());
    }

    public function test_each_side_shows_its_own_badge_icon_and_reference(): void
    {
        $stay = $this->stay();
        $booking = $this->booking();

        $this->actingAs($this->staff)->get("/admin/affectations/{$stay->id}")->assertOk()
            ->assertSee('kind-icon--affectation', false)->assertSee('file-badge--affectation', false)
            ->assertSee($stay->reference())->assertDontSee('Affectation #');
        $this->actingAs($this->staff)->get("/admin/reservations/{$booking->id}/edit")->assertOk()
            ->assertSee('kind-icon--reservation', false)->assertSee('file-badge--reservation', false)
            ->assertSee($booking->reference())->assertDontSee('Réservation #');
        $this->actingAs($this->staff)->get('/admin/affectations')->assertSee('kind-icon--affectation', false)->assertSee($stay->reference());
        $this->actingAs($this->staff)->get('/admin/reservations')->assertSee('kind-icon--reservation', false)->assertSee($booking->reference());
    }

    public function test_an_arrived_booking_shows_where_it_came_from(): void
    {
        $booking = $this->booking();
        $this->travelTo('2026-10-12 22:30:00');
        $booking->update(['status' => ReservationStatus::Enregistree]);

        $this->actingAs($this->staff)->get("/admin/affectations/{$booking->id}")->assertOk()
            ->assertSee($booking->reference())->assertSee('issue de la réservation')->assertSee($booking->bookingReference());
        $this->actingAs($this->staff)->get('/admin/reservations?onglet=historique')->assertOk();
    }

    public function test_invoices_say_which_kind_of_file_they_belong_to_and_can_be_filtered(): void
    {
        $stay = $this->stay();
        $booking = $this->booking();

        $this->actingAs($this->staff)->get('/admin/invoices')->assertOk()
            ->assertSee('Dossier')->assertSee($stay->reference())->assertSee($booking->reference())
            ->assertSee('file-badge--affectation', false)->assertSee('file-badge--reservation', false);

        $this->actingAs($this->staff)->get('/admin/invoices?type=affectation')->assertOk()
            ->assertSee($stay->invoice->number)->assertDontSee($booking->invoice->number);
        $this->actingAs($this->staff)->get('/admin/invoices?type=reservation')->assertOk()
            ->assertSee($booking->invoice->number)->assertDontSee($stay->invoice->number);

        $this->actingAs($this->staff)->get("/admin/invoices/{$booking->invoice->id}")->assertOk()
            ->assertSee('file-badge--reservation', false)
            ->assertSee(route('admin.reservations.edit', $booking), false);
    }

    public function test_guests_count_affectations_and_reservations_apart(): void
    {
        $this->stay();
        $this->booking('201');
        $this->booking('202');

        $this->actingAs($this->staff)->get('/admin/guests')->assertOk()
            ->assertViewHas('guests', fn ($guests) => $guests->first()->stays_count === 1 && $guests->first()->bookings_count === 2)
            ->assertSee('file-badge--affectation', false)->assertSee('file-badge--reservation', false);
    }

    public function test_dashboard_search_and_desk_lists_label_the_files(): void
    {
        $stay = $this->stay();
        $booking = Reservation::factory()->create(['guest_id' => $this->guest->id, 'room_id' => $this->room('301')->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000,
            'check_in' => '2026-10-08 15:00', 'duration' => 2, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);

        $this->actingAs($this->staff)->get('/admin')->assertOk()
            ->assertSee('(réservations)')->assertSee('(affectations)')->assertSee($booking->reference())->assertSee($stay->reference());
        $this->actingAs($this->staff)->get('/admin/recherche?q=koné')->assertOk()->assertSee('file-badge--', false);
        $this->actingAs($this->staff)->get('/admin/departs')->assertOk()->assertSee($stay->reference());
        $this->actingAs($this->staff)->get('/admin/arrivees')->assertOk()->assertSee($booking->reference());
        $this->actingAs($this->staff)->get('/admin/a-encaisser')->assertOk()
            ->assertSee('file-badge--affectation', false)->assertSee('file-badge--reservation', false);
    }
}
