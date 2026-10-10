<?php

namespace Tests\Feature\Admin;

use App\Models\Guest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<int, string>>
     */
    public static function pageRoutes(): array
    {
        return [
            ['/admin'],
            ['/admin/rooms/board'],
            ['/admin/rooms'],
            ['/admin/room-types'],
            ['/admin/guests'],
            ['/admin/reservations'],
            ['/admin/affectations'],
            ['/admin/invoices'],
        ];
    }

    #[DataProvider('pageRoutes')]
    public function test_admin_page_returns_200_for_authenticated_user(string $uri): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get($uri)->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_login_page_is_reachable_for_guests(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_user_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->actingAs($user)
            ->post('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_reservation_create_page_is_reachable(): void
    {
        $user = User::factory()->create();
        Room::factory()->create();
        Guest::factory()->create();

        $this->actingAs($user)->get('/admin/reservations/create')->assertOk();
    }

    public function test_invoice_resource_has_no_create_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/invoices/create')->assertNotFound();
    }
}
