<?php

namespace Tests\Feature\Admin;

use App\Enums\PricingMode;
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
 * « Planning » (V2): rooms × time, read only; blocks per file kind, names only on bookings not
 * yet started, links and pre-filled actions through the existing screens.
 */
class PlanningTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-18 10:00:00'); // a Wednesday
        $this->type = RoomType::factory()->create(['name' => 'Deluxe']);
    }

    private function room(string $number, array $attrs = []): Room
    {
        return Room::factory()->create(['number' => $number, 'room_type_id' => $this->type->id] + $attrs);
    }

    private function stay(Room $room, string $from, int $hours, array $attrs = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'room_id' => $room->id, 'pricing_mode' => PricingMode::Heure, 'duration' => $hours, 'unit_rate' => 5000,
            'check_in' => $from, 'status' => ReservationStatus::Enregistree, 'origin' => Reservation::ORIGIN_AFFECTATION,
            'guest_id' => Guest::factory()->create(['first_name' => 'Secret', 'last_name' => 'Logé'])->id,
        ], $attrs));
    }

    public function test_week_view_shows_rooms_by_type_and_files_as_blocks(): void
    {
        $r101 = $this->room('101');
        $r102 = $this->room('102');
        $this->room('10');
        $stay = $this->stay($r101, '2026-11-18 08:00', 4);
        $booking = Reservation::factory()->guaranteed()->create([
            'room_id' => $r102->id, 'pricing_mode' => PricingMode::Nuitee, 'duration' => 2, 'check_in' => '2026-11-20', 'status' => ReservationStatus::Confirmee,
            'origin' => Reservation::ORIGIN_RESERVATION, 'guest_id' => Guest::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné'])->id,
        ]);
        $cancelled = Reservation::factory()->create(['room_id' => $r102->id, 'check_in' => '2026-11-19 12:00', 'status' => ReservationStatus::Annulee, 'origin' => Reservation::ORIGIN_RESERVATION]);

        $response = $this->actingAs(User::factory()->create())->get(route('admin.planning.index'));
        $response->assertOk()
            ->assertSeeInOrder(['Deluxe', 'N°10', 'N°101', 'N°102'])            // natural order inside the type
            ->assertSee('Lun. 16/11')->assertSee('Dim. 22/11')                     // the week of the 18th
            ->assertSee('plan-block--stay', false)
            ->assertSee('plan-block--booking ', false)
            ->assertSee($stay->reference())
            ->assertSee($booking->reference().' · Awa Koné')                       // name on a booking…
            ->assertSee('href="'.$stay->fileUrl().'"', false)
            ->assertSee('href="'.$booking->fileUrl().'"', false)
            ->assertDontSee($cancelled->reference());                              // cancelled: hidden

        // …never on a stay (same rule as the room board), not even in the tooltip.
        $this->assertStringNotContainsString('Secret', $response->getContent());
    }

    public function test_unpaid_booking_overdue_stay_cleaning_and_out_of_service(): void
    {
        $r1 = $this->room('201');
        $r2 = $this->room('202');
        $this->room('203', ['status' => RoomStatus::HorsService]);
        $this->stay($r1, '2026-11-18 05:00', 2); // planned exit 07:00, still in at 10:00
        Reservation::factory()->create(['room_id' => $r2->id, 'pricing_mode' => PricingMode::Nuitee, 'duration' => 1, 'check_in' => '2026-11-19',
            'status' => ReservationStatus::EnAttente, 'origin' => Reservation::ORIGIN_RESERVATION]);

        $this->actingAs(User::factory()->create())->get(route('admin.planning.index', ['vue' => 'jour']))->assertOk()
            ->assertSee('is-overdue', false)->assertSee('(dépassée)')
            ->assertSee('plan-buffer', false)
            ->assertSee('plan-now', false)
            ->assertSee('Hors service');
        $this->actingAs(User::factory()->create())->get(route('admin.planning.index', ['vue' => 'semaine']))
            ->assertSee('plan-block--booking-unpaid', false)->assertSee('Non soldée');
    }

    public function test_day_and_month_views_navigation_and_filters(): void
    {
        $this->room('101');
        $other = RoomType::factory()->create(['name' => 'Suite']);
        Room::factory()->create(['number' => '301', 'room_type_id' => $other->id]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.planning.index', ['vue' => 'jour', 'date' => '2026-11-18']))->assertOk()
            ->assertSee('06h')->assertSee('05h')
            ->assertSee('date=2026-11-17', false)->assertSee('date=2026-11-19', false);
        $this->actingAs($user)->get(route('admin.planning.index', ['vue' => 'mois', 'date' => '2026-11-18']))->assertOk()
            ->assertSee('Du 01/11/2026 06h00')->assertSee('date=2026-12-01', false);
        $this->actingAs($user)->get(route('admin.planning.index', ['type' => $other->id]))->assertSee('N°301')->assertDontSee('N°101');

        // Forged values are ignored, never a crash.
        $this->actingAs($user)->get(route('admin.planning.index').'?vue[]=x&date[]=1&type[]=2&libres[]=1')->assertOk();
        $this->actingAs($user)->get(route('admin.planning.index', ['vue' => 'annee', 'date' => '2026-02-31x', 'type' => '1 or 1=1']))->assertOk()->assertSee('N°101');
        $this->actingAs($user)->get(route('admin.planning.index', ['date' => '9999-01-01']))->assertOk();
    }

    public function test_free_only_filter_and_phone_default_to_day_view(): void
    {
        $busy = $this->room('101');
        $this->room('102');
        $this->stay($busy, '2026-11-18 08:00', 4);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.planning.index', ['libres' => '1']))->assertSee('N°102')->assertDontSee('N°101');
        $this->actingAs($user)->withUnencryptedCookie(User::PHONE_COOKIE, '1')->get(route('admin.planning.index'))->assertSee('plan-grid--jour', false);
    }

    public function test_access_links_and_actions_follow_permissions(): void
    {
        $room = $this->room('101');
        $stay = $this->stay($room, '2026-11-18 08:00', 4);

        $reception = User::factory()->reception()->create();
        $this->actingAs($reception)->get(route('admin.planning.index'))->assertOk()
            ->assertSee('data-book-url="'.route('admin.reservations.create').'"', false)
            ->assertSee('data-assign-url="'.route('admin.rooms.board').'"', false);

        $this->actingAs(User::factory()->withoutRole()->create())->get(route('admin.planning.index'))->assertForbidden();

        // Menus.
        $this->actingAs(User::factory()->create())->get('/admin')->assertSee(route('admin.planning.index'), false);
        $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')->assertSee(route('admin.planning.index'), false);
        $this->assertNotNull($stay);
    }

    public function test_the_reservation_form_is_prefilled_from_a_free_spot(): void
    {
        $room = $this->room('101');
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.reservations.create', ['chambre' => $room->id, 'date' => '2026-11-20', 'heure' => '14:00']))->assertOk()
            ->assertSee('data-old-room="'.$room->id.'"', false)
            ->assertSee('value="2026-11-20"', false)
            ->assertSee('value="14:00"', false)
            ->assertSee('<option value="'.$this->type->id.'" selected', false);

        // A past slot or a room out of service pre-fills nothing; garbage is ignored.
        $this->actingAs($user)->get(route('admin.reservations.create', ['chambre' => $room->id, 'date' => '2026-11-01', 'heure' => '14:00']))->assertOk()
            ->assertDontSee('value="2026-11-01"', false);
        $out = $this->room('102', ['status' => RoomStatus::HorsService]);
        $this->actingAs($user)->get(route('admin.reservations.create', ['chambre' => $out->id]))->assertOk()->assertDontSee('data-old-room="'.$out->id.'"', false);
        $this->actingAs($user)->get(route('admin.reservations.create').'?chambre[]=1&date=x&heure=<script>')->assertOk()->assertDontSee('<script>', false);
    }
}
