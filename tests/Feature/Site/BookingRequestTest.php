<?php

namespace Tests\Feature\Site;

use App\Enums\RoomStatus;
use App\Models\BookingRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * « Réserver » on the website: online booking requests (no reservation, no invoice, no room held).
 */
class BookingRequestTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 14:00:00');
        $this->type = RoomType::factory()->create(['name' => 'Confort', 'stay_rate' => null]);
        $this->room = Room::factory()->create(['room_type_id' => $this->type->id, 'is_published' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'room_type_id' => $this->type->id,
            'pricing_mode' => 'nuitee',
            'check_in' => '2026-10-10',
            'duration' => 2,
            'adults' => 2,
            'children' => 0,
            'phone' => '07 01 02 03 04',
            'first_name' => 'Awa',
            'last_name' => 'Koné',
            'email' => 'awa@example.com',
            'message' => 'Arrivée vers 23h.',
            ...$overrides,
        ];
    }

    public function test_the_header_and_room_page_lead_to_the_booking_form(): void
    {
        $this->get('/')->assertOk()->assertSee(route('booking.create'), false)->assertSee('Réserver');
        $this->get($this->room->publicUrl())->assertOk()->assertSee(route('booking.create', ['type' => $this->type->id]), false);
    }

    public function test_the_form_offers_only_bookable_room_types(): void
    {
        RoomType::factory()->create(['name' => 'Type inactif', 'is_active' => false])->rooms()->create(['number' => 'X1', 'status' => RoomStatus::Disponible, 'is_published' => true]);
        RoomType::factory()->create(['name' => 'Type sans chambre publiée'])->rooms()->create(['number' => 'X2', 'status' => RoomStatus::Disponible]);
        RoomType::factory()->create(['name' => 'Type sans tarif', 'hourly_rate' => null, 'day_rate' => null, 'night_rate' => null, 'stay_rate' => null])
            ->rooms()->create(['number' => 'X3', 'status' => RoomStatus::Disponible, 'is_published' => true]);

        $this->get('/reservation')->assertOk()
            ->assertSee('Confort')
            ->assertSee('dès 5 000 FCFA')
            ->assertDontSee('Type inactif')
            ->assertDontSee('Type sans chambre publiée')
            ->assertDontSee('Type sans tarif')
            ->assertSee('"sejour":null', false);
    }

    public function test_without_bookable_types_the_page_says_so(): void
    {
        $this->room->update(['is_published' => false]);

        $this->get('/reservation')->assertOk()->assertSee('La réservation en ligne sera bientôt disponible');
    }

    public function test_a_request_is_recorded_without_reservation_invoice_or_held_room(): void
    {
        $response = $this->post('/reservation', $this->payload(['unit_rate' => 1, 'total_amount' => 1, 'status' => 'traitee']));

        $booking = BookingRequest::sole();
        $response->assertRedirect(URL::signedRoute('booking.show', $booking));

        $this->assertSame(BookingRequest::STATUS_PENDING, $booking->status);
        $this->assertSame('2026-10-10 22:00', $booking->check_in->format('Y-m-d H:i'));
        $this->assertSame('2026-10-12 12:00', $booking->check_out->format('Y-m-d H:i'));
        $this->assertSame('75000.00', (string) $booking->unit_rate);
        $this->assertSame('150000.00', (string) $booking->total_amount);
        $this->assertSame('Arrivée vers 23h.', $booking->message);
        $this->assertSame(0, Reservation::count());
        $this->assertDatabaseCount('invoices', 0);
        $this->assertTrue($this->room->fresh()->isAvailableNow());

        $this->get(URL::signedRoute('booking.show', $booking))->assertOk()
            ->assertSee('Demande envoyée')
            ->assertSee('07 01 02 03 04')
            ->assertSee('150 000 FCFA')
            ->assertSee('Confort');
        $this->get(route('booking.show', $booking))->assertForbidden();
    }

    public function test_hourly_and_day_formulas_take_a_date_and_time(): void
    {
        $this->post('/reservation', $this->payload(['pricing_mode' => 'journee', 'check_in' => '2026-10-09T08:30', 'duration' => 1]))->assertSessionHasNoErrors();

        $booking = BookingRequest::sole();
        $this->assertSame('2026-10-09 08:30', $booking->check_in->format('Y-m-d H:i'));
        $this->assertSame('2026-10-09 14:30', $booking->check_out->format('Y-m-d H:i'));
        $this->assertSame('20000.00', (string) $booking->total_amount);
    }

    public function test_only_the_phone_is_required_and_inputs_are_validated(): void
    {
        $this->post('/reservation', $this->payload(['phone' => '', 'first_name' => '', 'last_name' => '', 'email' => '']))->assertSessionHasErrors('phone');
        $this->post('/reservation', $this->payload(['phone' => 'abc']))->assertSessionHasErrors('phone');
        $this->post('/reservation', $this->payload(['email' => 'pas-un-email']))->assertSessionHasErrors('email');
        $this->post('/reservation', $this->payload(['check_in' => '2026-10-01']))->assertSessionHasErrors('check_in');
        $this->post('/reservation', $this->payload(['check_in' => '2028-01-01']))->assertSessionHasErrors('check_in');
        $this->post('/reservation', $this->payload(['pricing_mode' => 'sejour']))->assertSessionHasErrors('pricing_mode');
        $this->post('/reservation', $this->payload(['room_type_id' => 999]))->assertSessionHasErrors('room_type_id');
        $this->post('/reservation', $this->payload(['duration' => 0]))->assertSessionHasErrors('duration');
        $this->post('/reservation', $this->payload(['website' => 'http://spam']))->assertSessionHasErrors('website');
        $this->assertSame(0, BookingRequest::count());

        $this->post('/reservation', $this->payload(['first_name' => '', 'last_name' => '', 'email' => '', 'message' => '']))->assertSessionHasNoErrors();
        $this->assertSame('Nom non précisé', BookingRequest::sole()->fullName());
    }

    public function test_one_pending_request_per_phone(): void
    {
        $this->post('/reservation', $this->payload())->assertSessionHasNoErrors();
        $this->post('/reservation', $this->payload(['phone' => '0701020304', 'check_in' => '2026-10-20']))
            ->assertSessionHasErrors(['phone' => 'Une demande de réservation est déjà en cours avec ce numéro : la réception vous rappelle.']);

        BookingRequest::sole()->forceFill(['status' => BookingRequest::STATUS_REFUSED])->save();
        $this->post('/reservation', $this->payload(['check_in' => '2026-10-20']))->assertSessionHasNoErrors();
    }

    public function test_a_type_with_no_free_room_is_refused_without_revealing_anything(): void
    {
        Reservation::factory()->guaranteed()->create([
            'room_id' => $this->room->id, 'pricing_mode' => 'nuitee', 'unit_rate' => 75000,
            'check_in' => '2026-10-10 22:00', 'duration' => 1, 'status' => 'confirmee',
        ]);

        $this->post('/reservation', $this->payload())
            ->assertSessionHasErrors(['check_in' => "Aucune chambre de ce type n'est disponible sur cette période. Essayez d'autres dates ou un autre type de chambre."]);

        // Other dates are fine; a room out of service never counts as free.
        $this->post('/reservation', $this->payload(['check_in' => '2026-10-15']))->assertSessionHasNoErrors();
        $this->room->update(['status' => RoomStatus::HorsService]);
        $this->post('/reservation', $this->payload(['phone' => '0505050505', 'check_in' => '2026-10-20']))->assertSessionHasErrors('check_in');
    }

    public function test_the_follow_up_page_shows_the_outcome(): void
    {
        $this->post('/reservation', $this->payload());
        $booking = BookingRequest::sole();

        $booking->forceFill(['status' => BookingRequest::STATUS_REFUSED, 'refusal_reason' => 'Complet ce week-end.'])->save();
        $this->get(URL::signedRoute('booking.show', $booking))->assertOk()->assertSee('Demande non retenue')->assertSee('Complet ce week-end.');

        $booking->forceFill(['status' => BookingRequest::STATUS_HANDLED])->save();
        $this->get(URL::signedRoute('booking.show', $booking))->assertOk()->assertSee('Réservation enregistrée');
    }
}
