<?php

namespace Tests\Feature\Admin;

use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Desk forms (client request): payment lines, then — right under « Ajouter un mode » — the
 * discount and one « Montant reçu » field feeding the cash line's hidden payments[i][received].
 */
class PaymentFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_discount_and_cash_received_come_right_after_add_a_method(): void
    {
        Room::factory()->create(['room_type_id' => RoomType::factory()->create()->id]);
        $user = User::factory()->create();

        $board = $this->actingAs($user)->get(route('admin.rooms.board'))->assertOk();
        $board->assertSeeInOrder(['Règlement', 'data-stay-preview', 'name="payments[0][method]"', 'data-add-line', 'name="discount_value"', 'data-received-shared', 'data-payment-summary'], false)
            ->assertSee('<input type="hidden" name="payments[0][received]"', false);

        $this->get(route('admin.reservations.create'))->assertOk()
            ->assertSeeInOrder(['Règlement', 'data-booking-preview', 'Acompte', 'data-add-line', 'name="discount_value"', 'data-received-shared'], false);
    }

    public function test_the_cash_received_posted_through_the_hidden_field_is_recorded(): void
    {
        $this->travelTo('2026-10-11 10:00:00');
        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create(['hourly_rate' => 5000])->id]);

        $this->actingAs(User::factory()->create())->post(route('admin.rooms.assign', $room), [
            'assign_room_id' => $room->id, 'pricing_mode' => 'heure', 'duration' => 1, 'adults' => 1, 'children' => 0,
            'discount_type' => 'montant', 'discount_value' => '1 000',
            'payments' => [['method' => 'especes', 'amount' => '4 000', 'received' => '10 000']],
        ])->assertSessionHasNoErrors();

        $payment = Payment::sole();
        $this->assertSame('4000.00', (string) $payment->amount);
        $this->assertSame('6000.00', (string) $payment->change_given);
    }
}
