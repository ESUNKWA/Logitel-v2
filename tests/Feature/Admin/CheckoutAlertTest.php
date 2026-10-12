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

    public function test_overdue_stays_are_in_the_alarm_feed(): void
    {
        $this->travelTo('2026-10-07 14:10:00');
        $overdue = $this->stay('2026-10-07 12:00', 2);       // due 14:00
        $this->stay('2026-10-07 13:00', 3);                  // due 16:00, not yet
        $this->stay('2026-10-07 10:00', 1, ReservationStatus::Terminee); // already checked out

        $this->actingAs(User::factory()->reception()->create())
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

    public function test_snoozing_pauses_the_alarm_five_minutes_then_it_rings_again(): void
    {
        $this->travelTo('2026-10-07 14:10:00');
        $stay = $this->stay('2026-10-07 12:00', 2);
        $reception = User::factory()->reception()->create();

        $this->actingAs($reception)->postJson("/admin/alertes/departs/{$stay->id}/couper")
            ->assertOk()->assertJson(['snoozed_until' => '14h15']);
        $this->assertSame($reception->id, $stay->fresh()->overdue_acknowledged_by);

        // Still listed (the stay is still overdue), but paused for everyone.
        $this->actingAs(User::factory()->reception()->create())->getJson('/admin/alertes/departs')
            ->assertJsonCount(1)->assertJsonPath('0.snoozed_until', '14h15');

        // Five minutes later it rings again.
        $this->travelTo('2026-10-07 14:15:00');
        $this->actingAs($reception)->getJson('/admin/alertes/departs')
            ->assertJsonCount(1)->assertJsonPath('0.snoozed_until', null);
    }

    public function test_only_checking_out_or_extending_stops_the_alarm(): void
    {
        $this->travelTo('2026-10-07 14:10:00');
        $extended = $this->stay('2026-10-07 12:00', 2);
        $leaving = $this->stay('2026-10-07 12:00', 2);
        $reception = User::factory()->reception()->create();
        $this->actingAs($reception)->getJson('/admin/alertes/departs')->assertJsonCount(2);

        // Extended by one hour: quiet until the new departure time, then it rings (no snooze left).
        $this->actingAs($reception)->postJson("/admin/alertes/departs/{$extended->id}/couper")->assertOk();
        $extended->update(['duration' => 3]);
        $this->assertNull($extended->fresh()->overdue_acknowledged_at);

        // Checked out: gone.
        $this->actingAs($reception)->post("/admin/affectations/{$leaving->id}/check-out");
        $this->actingAs($reception)->getJson('/admin/alertes/departs')->assertJsonCount(0);

        $this->travelTo('2026-10-07 15:00:00');
        $this->actingAs($reception)->getJson('/admin/alertes/departs')
            ->assertJsonCount(1)->assertJsonPath('0.id', $extended->id)->assertJsonPath('0.snoozed_until', null);
    }

    public function test_the_alarm_rings_at_the_reception_and_never_for_the_manager(): void
    {
        $this->travelTo('2026-10-07 14:10:00');
        $this->stay('2026-10-07 12:00', 2);

        // Réception (granted by migration 2026_10_12_100000): the alarm panel is on every page.
        $this->actingAs(User::factory()->reception()->create())->get('/admin')->assertOk()
            ->assertSee('id="checkoutAlerts"', false);

        // Gérant (system role, every permission): no alarm, the dashboard still counts overdue stays.
        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()
            ->assertDontSee('id="checkoutAlerts"', false)
            ->assertSee('1 départ(s) dépassé(s)');

        // A role without the permission: no panel, no feed.
        $role = Role::create(['name' => 'Gouvernante']);
        $role->syncPermissions([Permission::RoomsView->value]);
        $housekeeper = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($housekeeper)->get('/admin')->assertOk()->assertDontSee('id="checkoutAlerts"', false);
        $this->actingAs($housekeeper)->getJson('/admin/alertes/departs')->assertForbidden();
    }

    public function test_checkout_and_extend_links_are_only_given_to_staff_who_can_handle_stays(): void
    {
        $this->travelTo('2026-10-07 14:10:00');
        $stay = $this->stay('2026-10-07 12:00', 2);

        $this->actingAs(User::factory()->reception()->create())->getJson('/admin/alertes/departs')
            ->assertJsonPath('0.checkout_url', route('admin.affectations.show', $stay).'#check-out')
            ->assertJsonPath('0.extend_url', route('admin.affectations.show', $stay).'#prolonger');
        $this->get(route('admin.affectations.show', $stay))->assertSee('id="check-out"', false)->assertSee('id="prolonger"', false);

        $role = Role::create(['name' => 'Veilleur']);
        $role->syncPermissions([Permission::AlertsCheckout->value]);

        $this->actingAs(User::factory()->create(['role_id' => $role->id]))
            ->getJson('/admin/alertes/departs')
            ->assertOk()
            ->assertJsonPath('0.checkout_url', null)
            ->assertJsonPath('0.extend_url', null);
    }
}
