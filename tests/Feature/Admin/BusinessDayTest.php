<?php

namespace Tests\Feature\Admin;

use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Support\BusinessDay;
use App\Support\HotelSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessDayTest extends TestCase
{
    use RefreshDatabase;

    private function stay(string $checkIn, ReservationStatus $status = ReservationStatus::Enregistree, int $amountHours = 2): Reservation
    {
        return Reservation::factory()->create([
            'pricing_mode' => PricingMode::Heure,
            'check_in' => $checkIn,
            'duration' => $amountHours,
            'unit_rate' => 10000,
            'status' => $status,
        ]);
    }

    public function test_business_day_starts_at_6_and_ends_at_5_59(): void
    {
        $this->assertSame('2026-10-07', BusinessDay::dateOf(Carbon::parse('2026-10-08 05:59'))->toDateString());
        $this->assertSame('2026-10-08', BusinessDay::dateOf(Carbon::parse('2026-10-08 06:00'))->toDateString());
        $this->assertSame('2026-10-08', BusinessDay::dateOf(Carbon::parse('2026-10-08 23:30'))->toDateString());
        $this->assertSame('05:59', BusinessDay::endTime());

        [$start, $end] = BusinessDay::bounds(Carbon::parse('2026-10-07'));
        $this->assertSame('2026-10-07 06:00', $start->format('Y-m-d H:i'));
        $this->assertSame('2026-10-08 06:00', $end->format('Y-m-d H:i'));
    }

    public function test_dashboard_today_counts_follow_the_business_day(): void
    {
        $this->travelTo('2026-10-08 02:00:00'); // still the business day of Oct 7

        $this->stay('2026-10-07 05:30', ReservationStatus::Confirmee); // previous business day
        $this->stay('2026-10-07 23:00', ReservationStatus::Confirmee); // today
        $this->stay('2026-10-08 03:00', ReservationStatus::Confirmee); // today (before 06:00)
        $this->stay('2026-10-08 07:00', ReservationStatus::Confirmee); // next business day

        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()->assertViewHas('todayCheckIns', 2);
    }

    public function test_monthly_revenue_follows_the_business_month(): void
    {
        $this->stay('2026-10-31 20:00', ReservationStatus::Confirmee); // 20 000, October
        $this->stay('2026-11-01 03:00', ReservationStatus::Confirmee); // 20 000, still October's last business day
        $this->stay('2026-11-01 09:00', ReservationStatus::Confirmee); // 20 000, November

        $this->travelTo('2026-10-31 21:00:00');
        $this->actingAs(User::factory()->create())->get('/admin')->assertViewHas('monthlyRevenue', fn ($v) => (float) $v === 40000.0);

        $this->travelTo('2026-11-01 10:00:00');
        $this->actingAs(User::factory()->create())->get('/admin')->assertViewHas('monthlyRevenue', fn ($v) => (float) $v === 20000.0);
    }

    public function test_invoice_is_dated_and_numbered_by_business_day(): void
    {
        $this->travelTo('2027-01-01 03:00:00'); // business day of Dec 31, 2026

        $invoice = $this->stay('2027-01-01 03:00')->invoice;

        $this->assertSame('2026-12-31', $invoice->issued_at->toDateString());
        $this->assertSame('INV-2026-0001', $invoice->number);
    }

    public function test_room_booked_before_06h_counts_as_reserved_for_the_current_business_day(): void
    {
        $this->travelTo('2026-10-07 22:00:00');
        $room = Room::factory()->create(['status' => RoomStatus::Disponible]);
        Reservation::factory()->guaranteed()->create([
            'room_id' => $room->id,
            'pricing_mode' => PricingMode::Heure,
            'check_in' => '2026-10-08 04:00', // next calendar day, same business day
            'duration' => 2,
            'status' => ReservationStatus::Confirmee,
        ]);

        $this->assertTrue(Room::query()->reservedNow()->whereKey($room->id)->exists());
    }

    public function test_manager_can_change_the_settings_and_they_apply_immediately(): void
    {
        $this->actingAs(User::factory()->create())->put('/admin/parametres', [
            'business_day_start' => '04:00',
            'night_from' => '20:00',
            'night_until' => '11:00',
            'day_hours' => 5,
            'stay_min_hours' => 24, 'cleaning_buffer_minutes' => 30,
        ])->assertRedirect(route('admin.settings.edit'))->assertSessionHasNoErrors();

        $this->assertSame('04:00', DB::table('settings')->where('key', 'business_day_start')->value('value'));
        $this->assertSame('04:00', config('hotel.business_day_start'));
        $this->assertSame('2026-10-08', BusinessDay::dateOf(Carbon::parse('2026-10-08 05:00'))->toDateString());

        // The pricing windows follow too: a nuitée may now start at 20:30 and a journée lasts 5 h.
        $this->travelTo('2026-10-07 20:30:00');
        $room = Room::factory()->create(['status' => RoomStatus::Disponible]);
        $this->actingAs(User::factory()->create())->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => 'Ama', 'new_last_name' => 'Kouassi', 'new_phone' => '0101',
            'pricing_mode' => 'nuitee', 'duration' => 1, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 75000]], // the room type's night rate
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-08 11:00', $room->reservations()->firstOrFail()->check_out->format('Y-m-d H:i'));
    }

    public function test_stored_settings_are_reloaded_on_boot(): void
    {
        DB::table('settings')->insert(['key' => 'day_hours', 'value' => '7', 'created_at' => now(), 'updated_at' => now()]);
        cache()->forget('hotel.settings');

        HotelSettings::apply();

        $this->assertSame(7, config('hotel.day_hours'));
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->put('/admin/parametres', [
            'business_day_start' => '25:00',
            'night_from' => '08:00',
            'night_until' => '12:00',
            'day_hours' => 0,
            'stay_min_hours' => 24, 'cleaning_buffer_minutes' => 30,
        ])->assertSessionHasErrors(['business_day_start', 'night_from', 'day_hours']);

        $this->assertSame(0, DB::table('settings')->count());
        $this->assertSame('06:00', config('hotel.business_day_start'));
    }

    public function test_only_staff_with_the_settings_permission_can_change_them(): void
    {
        $reception = User::factory()->reception()->create();

        $this->actingAs($reception)->get('/admin/parametres')->assertForbidden();
        $this->actingAs($reception)->put('/admin/parametres', [
            'business_day_start' => '04:00', 'night_from' => '22:00', 'night_until' => '12:00', 'day_hours' => 6, 'stay_min_hours' => 24, 'cleaning_buffer_minutes' => 30,
        ])->assertForbidden();

        $this->assertSame(0, DB::table('settings')->count());
        $this->actingAs(User::factory()->create())->get('/admin/parametres')->assertOk()->assertSee('05h59');
    }
}
