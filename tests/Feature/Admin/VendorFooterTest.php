<?php

namespace Tests\Feature\Admin;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Software provider credit at the bottom of every page of the application and of its login, on
 * every screen (client request, 2026-11-10) — never on the website nor on the printed receipt.
 */
class VendorFooterTest extends TestCase
{
    use RefreshDatabase;

    private const FOOTER = ['<footer class="vendor-footer', 'Prestataire : <strong>Ekwatech Solutions</strong>',
        'Contact : <a href="tel:0759947136">07 59 94 71 36</a>',
        'Site web : <a href="https://ekwatech.com" target="_blank" rel="noopener">ekwatech.com</a>'];

    public function test_the_footer_is_on_the_application_and_its_login(): void
    {
        $this->get('/admin/login')->assertOk()->assertSeeInOrder(['Se connecter', ...self::FOOTER], false);

        foreach ([User::factory()->create(), User::factory()->simpleUi()->create(), User::factory()->reception()->simpleUi()->create()] as $user) {
            foreach (['/admin', '/admin/rooms/board', '/admin/reservations'] as $url) {
                $this->actingAs($user)->get($url)->assertOk()->assertSeeInOrder(self::FOOTER, false);
            }
        }
    }

    public function test_not_on_the_website_nor_the_receipt(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Ekwatech Solutions');

        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create()->id]);
        $booking = Reservation::factory()->create(['room_id' => $room->id]);
        $this->actingAs(User::factory()->create())->get(route('admin.receipts.show', $booking))->assertOk()->assertDontSee('Ekwatech Solutions');
    }
}
