<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Mode simple / Mode complet", chosen per account: front-desk home with action tiles, short
 * navigation, and the task lists behind the tiles (departures, arrivals, to collect, search).
 */
class SimpleModeTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $this->type = RoomType::factory()->create(['name' => 'Confort', 'hourly_rate' => 5000, 'night_rate' => 25000]);
    }

    private function room(string $number, RoomStatus $status = RoomStatus::Disponible): Room
    {
        return Room::factory()->create(['number' => $number, 'room_type_id' => $this->type->id, 'status' => $status]);
    }

    private function stay(Room $room, string $in, string $out, string $guest = 'Awa Koné'): Reservation
    {
        [$first, $last] = explode(' ', $guest);
        $stay = Reservation::factory()->create([
            'guest_id' => Guest::factory()->create(['first_name' => $first, 'last_name' => $last, 'phone' => '0711223344'])->id,
            'room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000,
            'check_in' => $in, 'check_out' => $out, 'status' => ReservationStatus::Confirmee, 'origin' => Reservation::ORIGIN_AFFECTATION,
        ]);
        $stay->update(['status' => ReservationStatus::Enregistree]);

        return $stay;
    }

    private function booking(Room $room, string $in, string $out, string $guest = 'Jean Yao', string $status = 'confirmee'): Reservation
    {
        [$first, $last] = explode(' ', $guest);

        return Reservation::factory()->create([
            'guest_id' => Guest::factory()->create(['first_name' => $first, 'last_name' => $last])->id,
            'room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000,
            'check_in' => $in, 'check_out' => $out, 'status' => $status, 'origin' => Reservation::ORIGIN_RESERVATION,
        ]);
    }

    public function test_new_accounts_start_in_simple_mode(): void
    {
        $user = new User(['name' => 'Nouvelle', 'email' => 'n@katioka.ci', 'password' => 'Secret-2026-ok']);
        $user->save();

        $this->assertSame('simple', $user->fresh()->ui_mode);
        $this->assertTrue($user->fresh()->usesSimpleUi());
    }

    public function test_simple_mode_shows_the_front_desk_home_and_short_navigation(): void
    {
        $this->room('101');
        $user = User::factory()->simpleUi()->create(['name' => 'Aminata Koffi']);

        $this->actingAs($user)->get('/admin')->assertOk()->assertViewIs('admin.home')
            ->assertSee('class="ui-simple"', false)
            ->assertDontSee('class="admin-sidebar"', false)
            ->assertSee('Bonjour Aminata')
            ->assertSee('Affecter une chambre')->assertSee('1 chambre libre')
            ->assertSee('Sorties')->assertDontSee('home-tile__label">Demandes clients', false)->assertSee('Arrivées du jour')->assertDontSee('home-tile__label">Encaisser', false)
            ->assertDontSee('name="q"', false) // no client search on the home page (client request)
            ->assertSee('class="simple-tabbar"', false)
            ->assertSee('Mode complet');

        // The figures dashboard is still one click away.
        $this->actingAs($user)->get('/admin/tableau-de-bord')->assertOk()->assertViewIs('admin.dashboard');
    }

    public function test_the_phone_tab_bar_has_no_clients_entry_but_the_plus_menu_does(): void
    {
        $html = $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()->getContent();

        $tabbar = substr($html, strpos($html, '<nav class="simple-tabbar"'));
        $tabbar = substr($tabbar, 0, strpos($tabbar, '</nav>'));
        $this->assertStringContainsString('Accueil', $tabbar);
        $this->assertStringContainsString('Chambres', $tabbar);
        $this->assertStringNotContainsString('Clients', $tabbar);
        $this->assertStringNotContainsString(route('admin.guests.index'), $tabbar);

        // Still reachable on phones, from the « Plus » menu (Réception; the manager's phone menu drops it).
        $this->assertStringContainsString('<li class="simple-nav__phone-only"><a class="dropdown-item" href="'.route('admin.guests.index').'"><i class="far fa-users"></i> Clients</a></li>', $html);

        // Without guests.view, no entry at all.
        $role = Role::create(['name' => 'Ménage', 'slug' => 'menage']);
        $role->syncPermissions(['rooms.view']);
        $noGuests = User::factory()->simpleUi()->create();
        $noGuests->forceFill(['role_id' => $role->id])->save();
        $this->actingAs($noGuests)->get('/admin')->assertOk()->assertDontSee(route('admin.guests.index'), false);
    }

    public function test_on_phones_the_manager_s_plus_menu_drops_the_desk_screens(): void
    {
        $item = fn (string $class, string $route, string $icon, string $label) => '<li class="'.$class.'"><a class="dropdown-item" href="'.route($route).'"><i class="far '.$icon.'"></i> '.$label.'</a></li>';

        // Manager: desk screens only on wider screens; figures, QR code, site and configuration kept.
        $html = $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')->assertOk()->getContent();
        $this->assertStringContainsString($item('simple-nav__wide-only', 'admin.stay-requests.index', 'fa-mobile-alt', 'Demandes clients'), $html);
        $this->assertStringContainsString($item('simple-nav__wide-only', 'admin.invoices.index', 'fa-file-invoice-dollar', 'Factures'), $html);
        $this->assertStringContainsString($item('simple-nav__wide-only', 'admin.front-desk.collect', 'fa-coins', 'À encaisser'), $html);
        $this->assertStringNotContainsString('simple-nav__phone-only', $html); // no phone « Clients » entry
        $this->assertStringContainsString('<li><a class="dropdown-item" href="'.route('admin.stay-requests.qr').'"><i class="far fa-qrcode"></i> QR code client</a></li>', $html);
        $this->assertStringContainsString('<li><a class="dropdown-item" href="'.route('admin.revenue.index').'"><i class="far fa-cash-register"></i> Recettes</a></li>', $html);
        $this->assertStringContainsString('<li><a class="dropdown-item" href="'.route('admin.stats').'"><i class="far fa-chart-bar"></i> Tableau de bord chiffré</a></li>', $html);
        $this->assertStringContainsString(route('admin.booking-requests.index'), $html);
        $this->assertStringContainsString(route('admin.special-rates.index'), $html);
        $this->assertStringContainsString(route('admin.site.gallery.index'), $html);

        // The top « Demandes clients » icon is marked to be hidden on phones (CSS), still there on wider screens.
        $this->assertStringContainsString('class="simple-nav__icon simple-nav__requests "', $html);

        // Réception: unchanged.
        $html = $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()->getContent();
        $this->assertStringNotContainsString('simple-nav__wide-only', $html);
        $this->assertStringContainsString('<li class="simple-nav__phone-only"><a class="dropdown-item" href="'.route('admin.guests.index').'">', $html);
    }

    public function test_phones_always_get_the_simple_layout_without_touching_the_account_choice(): void
    {
        $user = User::factory()->create(); // chose « Mode complet »

        // On a phone (cookie written by the layout's script): simple layout and home.
        $this->actingAs($user)->withUnencryptedCookie(User::PHONE_COOKIE, '1')->get('/admin')->assertOk()
            ->assertViewIs('admin.home')
            ->assertSee('class="ui-simple"', false)
            ->assertDontSee('class="admin-sidebar"', false)
            ->assertSee('data-ui="simple" data-ui-forced="1"', false)
            ->assertSee('<script data-phone-ui>', false);
        $this->assertSame(User::UI_FULL, $user->fresh()->ui_mode); // the choice is kept for the computer

        // On a computer: the account's choice, and the script will drop a stale phone cookie.
        $this->unencryptedCookies = []; // test cookies persist between requests otherwise
        $this->actingAs($user)->get('/admin')->assertOk()
            ->assertViewIs('admin.dashboard')
            ->assertSee('class="admin-sidebar"', false)
            ->assertSee('data-ui="complet" data-ui-forced="0"', false);

        // A simple-mode account on a phone isn't « forced ».
        $this->actingAs(User::factory()->simpleUi()->create())->withUnencryptedCookie(User::PHONE_COOKIE, '1')->get('/admin')->assertOk()
            ->assertSee('data-ui="simple" data-ui-forced="0"', false);

        // Any other cookie value is ignored.
        $this->unencryptedCookies = [];
        $this->actingAs($user)->withUnencryptedCookie(User::PHONE_COOKIE, 'oui')->get('/admin')->assertOk()->assertViewIs('admin.dashboard');
    }

    public function test_full_mode_keeps_the_sidebar_and_figures_dashboard(): void
    {
        $user = User::factory()->create(); // factory = complet

        $this->actingAs($user)->get('/admin')->assertOk()->assertViewIs('admin.dashboard')
            ->assertSee('class="admin-sidebar"', false)
            ->assertDontSee('class="simple-tabbar"', false)
            ->assertSee('Mode simple');
    }

    public function test_each_user_switches_their_own_mode(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post('/admin/mode', ['mode' => 'simple'])->assertRedirect('/admin')->assertSessionHas('status', 'Mode simple activé.');
        $this->assertSame('simple', $user->fresh()->ui_mode);
        $this->assertSame('complet', $other->fresh()->ui_mode);
        $this->actingAs($user)->get('/admin')->assertViewIs('admin.home');

        $this->actingAs($user)->post('/admin/mode', ['mode' => 'complet'])->assertRedirect('/admin');
        $this->actingAs($user)->get('/admin')->assertViewIs('admin.dashboard');

        $this->actingAs($user)->post('/admin/mode', ['mode' => 'n importe quoi'])->assertSessionHasErrors('mode');
        $this->assertSame('complet', $user->fresh()->ui_mode);
    }

    public function test_tiles_and_menu_follow_permissions(): void
    {
        $role = Role::create(['name' => 'Gouvernante']);
        $role->syncPermissions([Permission::RoomsView->value, Permission::RoomsEdit->value]);
        $housekeeper = User::factory()->simpleUi()->create(['role_id' => $role->id]);

        $this->actingAs($housekeeper)->get('/admin')->assertOk()
            ->assertSee('Affecter une chambre')
            ->assertDontSee('Arrivées du jour')->assertDontSee('À encaisser')->assertDontSee('name="q"', false)
            ->assertDontSee('Configuration');

        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertSee('Arrivées du jour')->assertDontSee('home-tile__label">Encaisser', false)
            ->assertDontSee(route('admin.users.index'), false)->assertDontSee(route('admin.settings.edit'), false);

        $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')
            ->assertSee('Configuration')->assertSee(route('admin.hotel.edit'), false)->assertSee(route('admin.users.index'), false);
    }

    public function test_departures_list_today_and_overdue_stays_with_one_click_check_out(): void
    {
        $overdue = $this->stay($this->room('101'), '2026-10-08 05:00', '2026-10-08 09:00', 'Awa Koné');
        $today = $this->stay($this->room('102'), '2026-10-08 08:00', '2026-10-08 14:00', 'Moussa Traoré');
        $this->stay($this->room('103'), '2026-10-08 09:00', '2026-10-09 15:00', 'Paul Kouassi'); // leaves tomorrow afternoon
        $user = User::factory()->simpleUi()->create();

        $this->actingAs($user)->get('/admin')->assertSee('2 sorties')->assertSee('1 dépassée');
        $this->actingAs($user)->get('/admin/departs')->assertOk()
            ->assertSeeInOrder(['Chambre N°101', 'Dépassé', 'Chambre N°102'])
            ->assertDontSee('Paul Kouassi')
            ->assertSee(route('admin.affectations.check-out', $today), false)
            ->assertSee('Reste dû')->assertSee('Encaisser');

        $this->actingAs($user)->post(route('admin.affectations.check-out', $overdue))->assertRedirect();
        $this->assertSame(RoomStatus::Disponible, $overdue->room->fresh()->status);
        $this->actingAs($user)->get('/admin/departs')->assertDontSee('Awa Koné');
    }

    public function test_arrivals_list_todays_bookings_with_one_click_check_in(): void
    {
        $todayBooking = $this->booking($this->room('201'), '2026-10-08 14:00', '2026-10-08 16:00', 'Jean Yao');
        $this->booking($this->room('202'), '2026-10-09 14:00', '2026-10-09 16:00', 'Ali Bamba');           // tomorrow
        $this->booking($this->room('203'), '2026-10-08 15:00', '2026-10-08 17:00', 'Eva Gnaoré', 'annulee'); // cancelled
        $user = User::factory()->simpleUi()->create();

        $this->actingAs($user)->get('/admin')->assertSee('1 réservation');
        $this->actingAs($user)->get('/admin/arrivees')->assertOk()
            ->assertSee('Jean Yao')->assertSee('14h00')->assertSee('À payer')
            ->assertDontSee('Ali Bamba')->assertDontSee('Eva Gnaoré')
            ->assertSee(route('admin.reservations.check-in', $todayBooking), false);

        $this->travelTo('2026-10-08 14:05:00');
        $this->actingAs($user)->post(route('admin.reservations.check-in', $todayBooking))->assertRedirect(route('admin.affectations.show', $todayBooking));
        $this->actingAs($user)->get('/admin/arrivees')->assertSee('Aucune arrivée prévue');
    }

    public function test_to_collect_lists_open_balances_only(): void
    {
        $open = $this->stay($this->room('301'), '2026-10-08 08:00', '2026-10-08 12:00', 'Awa Koné');
        $paid = $this->stay($this->room('302'), '2026-10-08 08:00', '2026-10-08 12:00', 'Moussa Traoré');
        $paid->invoice->settle([['method' => 'wave', 'amount' => (float) $paid->invoice->amount]], now());
        $cancelled = $this->booking($this->room('303'), '2026-10-10 14:00', '2026-10-10 16:00', 'Eva Gnaoré');
        $cancelled->update(['status' => ReservationStatus::Annulee]);
        $user = User::factory()->simpleUi()->create();

        $this->actingAs($user)->get('/admin/a-encaisser')->assertOk()
            ->assertSee('Awa Koné')->assertSee('file-badge--affectation', false)->assertSee($open->reference())->assertSee('en cours')->assertSee('20 000 FCFA')
            ->assertSee(route('admin.invoices.show', $open->invoice), false)
            ->assertDontSee('Moussa Traoré')->assertDontSee('Eva Gnaoré');
    }

    public function test_one_search_box_finds_rooms_guests_and_invoices(): void
    {
        $stay = $this->stay($this->room('A12'), '2026-10-08 08:00', '2026-10-08 12:00', 'Awa Koné');
        $user = User::factory()->simpleUi()->create();

        $this->actingAs($user)->get('/admin/recherche?q=a12')->assertOk()
            ->assertSee('Chambre N°A12')->assertSee('Occupée')->assertSee(route('admin.affectations.show', $stay), false);
        $this->actingAs($user)->get('/admin/recherche?q=koné')->assertOk()->assertSee('Awa Koné')->assertSee('Dernier séjour');
        $this->actingAs($user)->get('/admin/recherche?q=0711')->assertOk()->assertSee('Awa Koné');
        $this->actingAs($user)->get('/admin/recherche?q='.strtolower($stay->invoice->number))->assertOk()->assertSee($stay->invoice->number);
        $this->actingAs($user)->get('/admin/recherche?q=zzz')->assertOk()->assertSee('Aucun résultat');
        $this->actingAs($user)->get('/admin/recherche')->assertOk()->assertSee('name="q"', false);
    }

    public function test_task_lists_are_permission_guarded(): void
    {
        $noRole = User::factory()->withoutRole()->simpleUi()->create();

        foreach (['/admin/departs', '/admin/arrivees', '/admin/a-encaisser', '/admin/recherche?q=a'] as $uri) {
            $this->actingAs($noRole)->get($uri)->assertForbidden();
        }
        $this->actingAs($noRole)->get('/admin')->assertOk()->assertDontSee('Affecter une chambre');
    }
}
