<?php

namespace Tests\Feature\Admin;

use App\Models\BookingRequest;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Demandes en ligne »: the réception turns a website booking request into a réservation
 * (usual form, pre-filled) or refuses it.
 */
class BookingRequestAdminTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 14:00:00');
        $this->type = RoomType::factory()->create(['name' => 'Confort']);
        $this->room = Room::factory()->create(['room_type_id' => $this->type->id, 'number' => '101', 'is_published' => true]);
    }

    private function booking(array $attributes = []): BookingRequest
    {
        return BookingRequest::create([
            'room_type_id' => $this->type->id, 'pricing_mode' => 'nuitee', 'check_in' => '2026-10-10 22:00', 'duration' => 2,
            'adults' => 2, 'children' => 1, 'unit_rate' => 75000, 'first_name' => 'Awa', 'last_name' => 'Koné',
            'phone' => '07 01 02 03 04', 'email' => 'awa@example.com', 'message' => 'Lit bébé svp', ...$attributes,
        ]);
    }

    private function reservationPayload(BookingRequest $booking, array $overrides = []): array
    {
        return [
            'booking_request_id' => $booking->id, 'new_phone' => $booking->phone, 'new_first_name' => 'Awa', 'new_last_name' => 'Koné',
            'pricing_mode' => 'nuitee', 'check_in' => '2026-10-10', 'duration' => 2, 'room_id' => $this->room->id,
            'adults' => 2, 'children' => 1, 'status' => 'confirmee', ...$overrides,
        ];
    }

    public function test_pending_requests_are_listed_and_counted(): void
    {
        $this->booking();
        $this->booking(['phone' => '0505', 'first_name' => '<b>X</b>'])->forceFill(['status' => BookingRequest::STATUS_REFUSED])->save();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.booking-requests.index'))->assertOk()
            ->assertSee('Awa Koné')
            ->assertSee('07 01 02 03 04')
            ->assertSee('Lit bébé svp')
            ->assertSee('Confort')
            ->assertSee('150 000 FCFA')
            ->assertSee(route('admin.reservations.create', ['demande' => BookingRequest::first()->id]), false)
            ->assertDontSee('&lt;b&gt;X', false);

        $this->get(route('admin.booking-requests.index', ['onglet' => 'refusees']))->assertOk()->assertSee('&lt;b&gt;X&lt;/b&gt;', false);
        $this->get(route('admin.reservations.index'))->assertOk()->assertSee('1 demande de réservation en ligne');
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Demandes en ligne');
    }

    public function test_the_reservation_form_is_prefilled_from_the_request(): void
    {
        $booking = $this->booking();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.reservations.create', ['demande' => $booking->id]))->assertOk()
            ->assertSee('demande en ligne n°'.$booking->id)
            ->assertSee('name="booking_request_id" value="'.$booking->id.'"', false)
            ->assertSee('value="07 01 02 03 04"', false)
            ->assertSee('value="2026-10-10"', false)
            ->assertSee('data-preferred-type="'.$this->type->id.'"', false)
            ->assertSee('Lit bébé svp');

        // An existing client with the same number is picked instead of a new one.
        $guest = Guest::factory()->create(['first_name' => 'Awa', 'last_name' => 'Déjà', 'phone' => '0701020304']);
        $this->get(route('admin.reservations.create', ['demande' => $booking->id]))->assertOk()
            ->assertSee('name="guest_id" value="'.$guest->id.'"', false);
    }

    public function test_saving_the_reservation_marks_the_request_handled_once(): void
    {
        $booking = $this->booking();
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('admin.reservations.store'), $this->reservationPayload($booking))->assertSessionHasNoErrors();

        $reservation = Reservation::sole();
        $booking->refresh();
        $this->assertSame(BookingRequest::STATUS_HANDLED, $booking->status);
        $this->assertSame($reservation->id, $booking->reservation_id);
        $this->assertSame($user->id, $booking->handled_by);
        $this->assertSame(Reservation::ORIGIN_RESERVATION, $reservation->origin);
        $this->assertSame('150000.00', (string) $reservation->total_amount);

        // Handled once: a second submission is refused and creates nothing.
        $this->post(route('admin.reservations.store'), $this->reservationPayload($booking, ['check_in' => '2026-10-20']))
            ->assertSessionHasErrors(['booking_request_id' => 'Cette demande en ligne a déjà été traitée.']);
        $this->assertSame(1, Reservation::count());
        $this->get(route('admin.reservations.create', ['demande' => $booking->id]))->assertRedirect(route('admin.booking-requests.index'));

        $this->get(route('admin.booking-requests.index', ['onglet' => 'traitees']))->assertOk()->assertSee($reservation->reference());
    }

    public function test_a_failed_reservation_leaves_the_request_pending(): void
    {
        $booking = $this->booking();
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.reservations.store'), $this->reservationPayload($booking, ['check_in' => '2026-10-01']))->assertSessionHasErrors('check_in');

        $this->assertSame(BookingRequest::STATUS_PENDING, $booking->fresh()->status);
        $this->assertSame(0, Reservation::count());
    }

    public function test_reservations_without_a_request_still_work(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.reservations.store'), collect($this->reservationPayload($this->booking()))->except('booking_request_id')->all())->assertSessionHasNoErrors();

        $this->assertSame(1, Reservation::count());
        $this->assertSame(BookingRequest::STATUS_PENDING, BookingRequest::sole()->status);
    }

    public function test_refusing_a_request(): void
    {
        $booking = $this->booking();
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('admin.booking-requests.refuse', $booking), ['refusal_reason' => ' Complet ce week-end. '])
            ->assertRedirect(route('admin.booking-requests.index'))->assertSessionHas('status');

        $booking->refresh();
        $this->assertSame(BookingRequest::STATUS_REFUSED, $booking->status);
        $this->assertSame('Complet ce week-end.', $booking->refusal_reason);
        $this->assertSame($user->id, $booking->handled_by);

        $this->post(route('admin.booking-requests.refuse', $booking))->assertSessionHasErrors('refusal_reason');
        $this->get(route('admin.reservations.create', ['demande' => $booking->id]))->assertRedirect(route('admin.booking-requests.index'));
    }

    public function test_permissions(): void
    {
        $booking = $this->booking();

        $this->actingAs(User::factory()->withoutRole()->create());
        $this->get(route('admin.booking-requests.index'))->assertForbidden();
        $this->post(route('admin.booking-requests.refuse', $booking))->assertForbidden();
        $this->assertSame(BookingRequest::STATUS_PENDING, $booking->fresh()->status);

        // Réception (reservations granted by default) can handle them.
        $this->actingAs(User::factory()->reception()->create());
        $this->get(route('admin.booking-requests.index'))->assertOk();
    }
}
