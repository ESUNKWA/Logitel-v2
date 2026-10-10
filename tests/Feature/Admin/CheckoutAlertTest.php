<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutAlertTest extends TestCase
{
    use RefreshDatabase;

    private function stay(string $checkIn, int $hours, ReservationStatus $status = ReservationStatus::Enregistree): Reservation
    {
        return Reservation::factory()->create([
            'room_id' => Room::factory()->create(['number' => 'ALR-'.fake()->unique()->numberBetween(1, 999)])->id,
            'guest_id' => Guest::factory()->create(['first_name' => 'Aya', 'last_name' => 'Bamba', 'phone' => '0707070707'])->id,
            'pricing_mode' => PricingMode::Heure,
            'check_in' => $checkIn,
            'duration' => $hours,
            'unit_rate' => 5000,
            'status' => $status,
        ]);
    }

    public function test_manager_is_alerted_when_departure_time_is_reached(): void
    {
        $this->travelTo('2026-10-07 14:10:00');
        $overdue = $this->stay('2026-10-07 12:00', 2);       // due 14:00
        $this->stay('2026-10-07 13:00', 3);                  // due 16:00, not yet
        $this->stay('2026-10-07 10:00', 1, ReservationStatus::Terminee); // already checked out

        $this->actingAs(User::factory()->create())
            ->getJson('/admin/alertes/departs')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => $overdue->id,
                'room' => $overdue->room->number,
                'guest' => 'Aya Bamba',
                'phone' => '0707070707',
                'check_out' => '07/10/2026 14h00',
                'overdue_minutes' => 10,
                'billing' => '2 heures × 5 000 FCFA',
            ]);
    }

    public function test_acknowledged_alert_stops_until_the_stay_is_extended(): void
    {
        $this->travelTo('2026-10-07 14:10:00');
        $stay = $this->stay('2026-10-07 12:00', 2);
        $manager = User::factory()->create();

        $this->actingAs($manager)->postJson("/admin/alertes/departs/{$stay->id}/vu")->assertOk();

        $stay->refresh();
        $this->assertNotNull($stay->overdue_acknowledged_at);
        $this->assertSame($manager->id, $stay->overdue_acknowledged_by);
        $this->actingAs($manager)->getJson('/admin/alertes/departs')->assertJsonCount(0);

        // Extended by one hour, then that new departure time is reached: the alarm rings again.
        $stay->update(['duration' => 3]);
        $this->assertNull($stay->fresh()->overdue_acknowledged_at);
        $this->actingAs($manager)->getJson('/admin/alertes/departs')->assertJsonCount(0);

        $this->travelTo('2026-10-07 15:00:00');
        $this->actingAs($manager)->getJson('/admin/alertes/departs')->assertJsonCount(1);
    }

    public function test_checking_out_clears_the_alert(): void
    {
        $this->travelTo('2026-10-07 14:10:00');
        $stay = $this->stay('2026-10-07 12:00', 2);
        $manager = User::factory()->create();

        $this->actingAs($manager)->post("/admin/affectations/{$stay->id}/check-out");

        $this->actingAs($manager)->getJson('/admin/alertes/departs')->assertJsonCount(0);
    }

    public function test_only_staff_with_the_alert_permission_receive_it(): void
    {
        $this->travelTo('2026-10-07 14:10:00');
        $stay = $this->stay('2026-10-07 12:00', 2);
        $reception = User::factory()->reception()->create();

        $this->actingAs($reception)->getJson('/admin/alertes/departs')->assertForbidden();
        $this->actingAs($reception)->postJson("/admin/alertes/departs/{$stay->id}/vu")->assertForbidden();
        $this->assertNull($stay->fresh()->overdue_acknowledged_at);

        $this->actingAs($reception)->get('/admin')->assertOk()->assertDontSee('id="checkoutAlerts"', false);
        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()->assertSee('id="checkoutAlerts"', false);
    }

    public function test_open_link_is_only_given_to_staff_who_can_check_out(): void
    {
        $this->travelTo('2026-10-07 14:10:00');
        $this->stay('2026-10-07 12:00', 2);

        $role = Role::create(['name' => 'Veilleur']);
        $role->syncPermissions([Permission::AlertsCheckout->value]);

        $this->actingAs(User::factory()->create(['role_id' => $role->id]))
            ->getJson('/admin/alertes/departs')
            ->assertOk()
            ->assertJsonPath('0.open_url', null);
    }
}
