<?php

namespace Tests\Feature\Admin;

use App\Enums\Department;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Sale;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Money figures on the home page (simple mode) and per payment method on the figures dashboard:
 * cash of the business day by method, month to date, open balances, occupancy.
 */
class HomeFinanceTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $this->type = RoomType::factory()->create(['hourly_rate' => 5000]);
    }

    private function stay(string $number, int $hours, array $payments, string $paidAt = '2026-10-08 09:00'): Reservation
    {
        $room = Room::factory()->create(['number' => $number, 'room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);
        $stay = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000,
            'check_in' => '2026-10-08 08:00', 'duration' => $hours, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_AFFECTATION]);
        $stay->update(['status' => ReservationStatus::Enregistree]);
        if ($payments) {
            $this->travelTo($paidAt);
            $stay->invoice->settle($payments, now());
            $this->travelTo('2026-10-08 10:00:00');
        }

        return $stay;
    }

    public function test_the_home_page_shows_the_money_of_the_day_by_payment_method(): void
    {
        // Mixed payment = one "encaissement" of two lines.
        $this->stay('101', 4, [['method' => 'especes', 'amount' => 15000, 'received' => 15000], ['method' => 'wave', 'amount' => 5000]]);
        $this->stay('102', 2, [['method' => 'orange_money', 'amount' => 10000]]);
        $this->stay('103', 2, [['method' => 'wave', 'amount' => 4000]], '2026-10-07 20:00'); // previous business day, same month
        $this->stay('104', 3, []); // nothing paid: 15 000 outstanding
        Room::factory()->create(['number' => 'HS-1', 'room_type_id' => $this->type->id, 'status' => RoomStatus::HorsService]);

        $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertViewHas('finance', fn ($f) => $f['today'] === 30000.0 && $f['todayCount'] === 2 && $f['month'] === 34000.0
                && $f['outstanding'] === 21000.0 && $f['outstandingCount'] === 2)
            ->assertSeeInOrder(['Recette du jour', '30 000', '2 encaissement(s)'])
            ->assertSeeInOrder(['Chambres occupées', '4', '/ 4', '100 %'])
            ->assertSeeInOrder(['Recette du mois', '34 000'])
            ->assertSeeInOrder(['Reste à encaisser', '21 000', '2 facture(s)'])
            ->assertSeeInOrder(['Recette du jour par mode de paiement', 'Espèces', '15 000 F', '50 %', 'Orange Money', '10 000 F', '33 %', 'Wave', '5 000 F', '17 %']);
    }

    private function book(string $number, string $checkIn): Reservation
    {
        $room = Room::factory()->create(['number' => $number, 'room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);

        return Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000,
            'check_in' => $checkIn, 'duration' => 2, 'status' => 'confirmee']);
    }

    private function venueBooking(Venue $venue, string $start, string $end): VenueBooking
    {
        return VenueBooking::book(['venue_id' => $venue->id, 'start_at' => Carbon::parse($start), 'end_at' => Carbon::parse($end),
            'formula' => VenueBooking::FORMULA_HALF, 'participants' => 10, 'client_name' => 'M. Test', 'client_phone' => '0700000000',
            'status' => VenueBooking::STATUS_CONFIRMED, 'quantities' => [], 'discount' => '0'], User::factory()->create());
    }

    public function test_the_reception_sees_what_is_due_by_the_end_of_the_business_day(): void
    {
        $this->stay('101', 4, [['method' => 'especes', 'amount' => 5000, 'received' => 5000]]); // in-house: 15 000 left
        $this->stay('102', 2, [['method' => 'wave', 'amount' => 10000]]); // settled: nothing
        $this->book('103', '2026-10-08 18:00'); // arrival today: 10 000
        $this->book('104', '2026-10-12 14:00'); // future booking: left out
        Sale::record(Department::Restauration, [], 'Table 4', null, '0', null, [['label' => 'Poulet braisé', 'unit_price' => '6000', 'quantity' => 1]]); // 6 000
        $hall = Venue::create(['name' => 'Salle Baobab', 'capacity' => 50, 'half_day_rate' => 100000, 'day_rate' => 180000]);
        $this->venueBooking($hall, '2026-10-08 14:00', '2026-10-08 19:00'); // event today: 100 000
        $this->venueBooking($hall, '2026-10-15 08:00', '2026-10-15 13:00'); // future event: left out

        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertViewHas('dueToday', [131000.0, 4])
            ->assertSeeInOrder(['Reste à encaisser', '131 000', '4 factures à régler aujourd\'hui']);

        // Same card in the live refresh of the home figures.
        $this->getJson('/admin/chiffres-en-direct?blocs=home-figures')->assertOk()
            ->assertJsonPath('blocks.home-figures', fn (string $html) => str_contains($html, 'data-due-today') && str_contains($html, '131 000'));
    }

    public function test_the_collect_page_filters_what_is_due_today(): void
    {
        $inHouse = $this->stay('101', 4, []); // due today
        $arrival = $this->book('103', '2026-10-08 18:00'); // due today
        $future = $this->book('104', '2026-10-12 14:00'); // later
        $hall = Venue::create(['name' => 'Salle Baobab', 'capacity' => 50, 'half_day_rate' => 100000, 'day_rate' => 180000]);
        $laterEvent = $this->venueBooking($hall, '2026-10-15 08:00', '2026-10-15 13:00'); // later
        $reception = User::factory()->reception()->simpleUi()->create();

        // The home card opens the « Aujourd'hui » tab.
        $this->actingAs($reception)->get('/admin')->assertSee(route('admin.front-desk.collect', ['periode' => 'aujourdhui']), false);

        $today = $this->get('/admin/a-encaisser?periode=aujourdhui')->assertOk()
            ->assertViewHas('period', 'aujourdhui')
            ->assertViewHas('counts', ['aujourdhui' => 2, 'tout' => 4])
            ->assertSee($inHouse->invoice->number)->assertSee($arrival->invoice->number)
            ->assertDontSee($future->invoice->number)->assertDontSee($laterEvent->sale->invoice->number);
        $today->assertSeeInOrder(['Aujourd\'hui', '2', 'Tout', '4']);

        $this->get('/admin/a-encaisser')->assertOk()
            ->assertViewHas('period', 'tout')
            ->assertSee($future->invoice->number)->assertSee($laterEvent->sale->invoice->number);
    }

    public function test_the_collect_page_searches_every_text_of_the_line(): void
    {
        $bamba = $this->book('203', '2026-10-08 18:00');
        $bamba->guest->update(['first_name' => 'Fatou', 'last_name' => 'Bamba', 'phone' => '07 48 22 19 03']);
        $other = $this->book('204', '2026-10-12 14:00');
        $other->guest->update(['first_name' => 'Serge', 'last_name' => 'Kouadio', 'phone' => '0707312690']);
        $table = Sale::record(Department::Restauration, [], 'Table 4', null, '0', null, [['label' => 'Garba', 'unit_price' => '2000', 'quantity' => 1]]);
        $hall = Venue::create(['name' => 'Salle Baobab', 'capacity' => 50, 'half_day_rate' => 100000, 'day_rate' => 180000]);
        $event = VenueBooking::book(['venue_id' => $hall->id, 'start_at' => Carbon::parse('2026-10-15 08:00'), 'end_at' => Carbon::parse('2026-10-15 13:00'),
            'formula' => VenueBooking::FORMULA_HALF, 'participants' => 10, 'client_name' => 'Mme Yao', 'client_phone' => '0101010101',
            'organization' => 'Orange CI', 'status' => VenueBooking::STATUS_OPTION, 'quantities' => [], 'discount' => '0'], User::factory()->create());

        $this->actingAs(User::factory()->reception()->simpleUi()->create());
        $found = fn (string $q, string $period = '') => $this->get('/admin/a-encaisser?q='.urlencode($q).($period ? '&periode='.$period : ''))->assertOk()
            ->viewData('invoices')->getCollection()->pluck('number')->all();

        $this->assertSame([$bamba->invoice->number], $found('bamba 203')); // two words, two fields
        $this->assertSame([$bamba->invoice->number], $found('FATOU'));
        $this->assertSame([$bamba->invoice->number], $found('0748221903')); // phone typed without spaces
        $this->assertSame([$other->invoice->number], $found('kouad'));
        $this->assertSame([$table->invoice->number], $found('table 4'));
        $this->assertSame([$event->sale->invoice->number], $found('orange'));
        $this->assertSame([$bamba->invoice->number], $found($bamba->invoice->number));
        $this->assertSame([], $found('kouadio', 'aujourdhui')); // future booking: not in « Aujourd'hui »
        $this->assertSame([], $found('introuvable'));

        $this->get('/admin/a-encaisser?q=kouadio&periode=aujourdhui')
            ->assertViewHas('counts', ['aujourdhui' => 0, 'tout' => 1])
            ->assertSee('Aucune facture à encaisser ne correspond à « kouadio »', false);
    }

    public function test_the_invoice_payment_form_opens_with_what_is_left_to_pay(): void
    {
        $stay = $this->stay('101', 4, [['method' => 'especes', 'amount' => 5000, 'received' => 5000]]); // 20 000 - 5 000

        $this->actingAs(User::factory()->reception()->simpleUi()->create())
            ->get(route('admin.invoices.show', $stay->invoice))->assertOk()
            ->assertSee('name="payments[0][amount]" value="15000"', false);

        // Settling it fully leaves no form to fill.
        $stay->invoice->settle([['method' => 'wave', 'amount' => 15000]], now());
        $this->get(route('admin.invoices.show', $stay->invoice))->assertOk()
            ->assertDontSee('name="payments[0][amount]"', false);
    }

    public function test_the_due_today_card_says_when_nothing_is_left(): void
    {
        $this->stay('101', 2, [['method' => 'wave', 'amount' => 10000]]);
        $this->book('104', '2026-10-12 14:00');

        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertViewHas('dueToday', [0.0, 0])
            ->assertSeeInOrder(['Reste à encaisser', '0', 'Rien à encaisser aujourd\'hui']);
    }

    public function test_the_manager_keeps_the_full_balance_instead(): void
    {
        $this->book('104', '2026-10-12 14:00');

        $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertViewHas('dueToday', null)
            ->assertDontSee('data-due-today', false)
            ->assertSeeInOrder(['Reste à encaisser', '10 000', '1 facture(s)']);
    }

    public function test_todays_discounts_are_mentioned(): void
    {
        $stay = $this->stay('101', 4, []);
        $stay->update(['discount_type' => 'montant', 'discount_value' => 2000]);

        $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')->assertSee('2 000 F de remises');
    }

    public function test_money_is_neither_shown_nor_computed_without_the_revenue_permission(): void
    {
        $this->stay('101', 2, [['method' => 'wave', 'amount' => 10000]]);

        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertViewHas('finance', null)
            ->assertDontSee('Recette du jour')->assertDontSee('10 000')
            ->assertSee('Chambres occupées'); // occupancy stays visible
    }

    public function test_an_empty_day_shows_zeroes(): void
    {
        $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertSeeInOrder(['Recette du jour', '0', '0 encaissement(s)'])
            ->assertSeeInOrder(['Espèces', '0 F', '0 %']);
    }

    public function test_the_figures_dashboard_shows_cash_per_payment_method(): void
    {
        $this->stay('101', 2, [['method' => 'orange_money', 'amount' => 10000]]);
        $this->stay('102', 2, [['method' => 'wave', 'amount' => 4000]], '2026-10-07 20:00');

        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()->assertViewIs('admin.dashboard')
            ->assertSee('Encaissé par mode de paiement')
            ->assertSeeInOrder(['Orange Money', '10 000', '71 %', '10 000 aujourd', 'Wave', '4 000', '29 %', '0 aujourd']);
    }
}
