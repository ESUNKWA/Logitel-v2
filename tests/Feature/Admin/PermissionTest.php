<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Admin routes intentionally reachable without a specific permission.
     */
    private const UNGUARDED_ROUTES = [
        'admin.login',
        'admin.login.attempt',
        'admin.logout',
        'admin.dashboard',
        'admin.stats',          // same page as the full-mode home (money figures gated inside)
        'admin.live-figures',   // live refresh of those same figures, gated the same way
        'admin.ui-mode.update', // each staff member's own layout preference
        'admin.profile.edit',
        'admin.profile.update',
        'admin.profile.password.update',
        'admin.pwa.manifest',       // installable application: public, the login page needs them
        'admin.pwa.service-worker',
        'admin.pwa.offline',
        'admin.pwa.asset-links',    // Android APK verification (no data)
    ];

    public function test_every_admin_route_requires_a_permission(): void
    {
        $unguarded = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDefinition $route) => str_starts_with((string) $route->getName(), 'admin.'))
            ->reject(fn (RouteDefinition $route) => in_array($route->getName(), self::UNGUARDED_ROUTES, true))
            ->reject(fn (RouteDefinition $route) => collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'can:')))
            ->map(fn (RouteDefinition $route) => $route->getName())
            ->values()
            ->all();

        $this->assertSame([], $unguarded, 'Admin routes without a can:<permission> middleware.');
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function receptionAllowedPages(): array
    {
        return [
            ['/admin'],
            ['/admin/profil'],
            ['/admin/rooms/board'],
            ['/admin/rooms'],
            ['/admin/guests'],
            ['/admin/guests/create'],
            ['/admin/reservations'],
            ['/admin/reservations/create'],
            ['/admin/affectations'],
            ['/admin/invoices'],
        ];
    }

    #[DataProvider('receptionAllowedPages')]
    public function test_reception_can_open_front_desk_pages(string $uri): void
    {
        $this->actingAs(User::factory()->reception()->create())->get($uri)->assertOk();
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function receptionForbiddenPages(): array
    {
        return [
            ['/admin/room-types'],
            ['/admin/room-types/create'],
            ['/admin/rooms/create'],
            ['/admin/users'],
            ['/admin/users/create'],
            ['/admin/roles'],
            ['/admin/roles/create'],
        ];
    }

    #[DataProvider('receptionForbiddenPages')]
    public function test_reception_is_denied_management_pages(string $uri): void
    {
        $this->actingAs(User::factory()->reception()->create())->get($uri)->assertForbidden();
    }

    public function test_reception_cannot_delete_a_guest(): void
    {
        $guest = Guest::factory()->create();

        $this->actingAs(User::factory()->reception()->create())
            ->delete("/admin/guests/{$guest->id}")
            ->assertForbidden();

        $this->assertModelExists($guest);
    }

    public function test_reception_cannot_cancel_a_reservation(): void
    {
        $reservation = Reservation::factory()->create(['status' => ReservationStatus::Confirmee]);

        $this->actingAs(User::factory()->reception()->create())
            ->post("/admin/reservations/{$reservation->id}/cancel")
            ->assertForbidden();

        $this->assertSame(ReservationStatus::Confirmee, $reservation->fresh()->status);
    }

    public function test_reception_can_record_but_not_delete_a_payment(): void
    {
        $reception = User::factory()->reception()->create();
        $invoice = Reservation::factory()->create()->invoice;

        $this->actingAs($reception)->post("/admin/invoices/{$invoice->id}/payments", [
            'amount' => 1000,
            'method' => 'especes',
            'paid_at' => now()->format('Y-m-d H:i'),
        ])->assertRedirect();

        $payment = Payment::firstOrFail();

        $this->actingAs($reception)
            ->delete("/admin/invoices/{$invoice->id}/payments/{$payment->id}")
            ->assertForbidden();

        $this->assertModelExists($payment);
    }

    public function test_user_without_role_only_reaches_dashboard_and_profile(): void
    {
        $user = User::factory()->withoutRole()->create();

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/profil')->assertOk();
        $this->actingAs($user)->get('/admin/rooms/board')->assertForbidden();
        $this->actingAs($user)->get('/admin/guests')->assertForbidden();
    }

    public function test_revenue_and_admin_menu_are_hidden_from_reception(): void
    {
        $this->actingAs(User::factory()->reception()->create())
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Recettes encaissées ce mois')
            ->assertDontSee('Recettes du jour')
            ->assertDontSee('Reste à encaisser')
            ->assertDontSee("Chiffre d'affaires", false)
            ->assertDontSee(route('admin.users.index'))
            ->assertDontSee(route('admin.roles.index'));

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Recettes encaissées ce mois')
            ->assertSee('Reste à encaisser')
            ->assertSee(route('admin.users.index'));
    }
}
