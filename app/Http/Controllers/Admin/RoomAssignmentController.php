<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomAssignmentRequest;
use App\Models\Guest;
use App\Models\Room;
use App\Support\RoomAssignment;
use Illuminate\Http\RedirectResponse;

class RoomAssignmentController extends Controller
{
    /**
     * Assign a room to a client from the room board ("affectation"): the guest moves in now,
     * so the stay is created and checked in straight away (room → occupee via the Reservation
     * model events, which also create the invoice). Future bookings belong to the reservations
     * module, not here. The writing itself is shared with the validation of a guest's request
     * (see App\Support\RoomAssignment).
     */
    public function store(RoomAssignmentRequest $request, Room $room): RedirectResponse
    {
        $data = $request->validated();

        // Taking money at check-in needs the payment permission too (the route only checks reservations.manage).
        abort_if($request->paymentLines() !== [] && $request->user()->cannot('payments.create'), 403);

        if ($room->status !== RoomStatus::Disponible) {
            return back()
                ->withErrors(['pricing_mode' => "La chambre N°{$room->number} est « {$room->status->getLabel()} » : elle ne peut pas être affectée."])
                ->withInput();
        }

        [$start, $end] = $request->period();

        if ($error = $room->availabilityError($start, $end)) {
            return back()->withErrors(['duration' => $error])->withInput();
        }

        $reservation = RoomAssignment::create($room, $request, fn () => $data['guest_id'] ?? $this->createGuest($data)->id);

        return redirect()
            ->route('admin.rooms.board')
            ->with('receipt', $reservation->id)
            ->with('status', "Chambre N°{$room->number} affectée ({$reservation->billingLabel()} = "
                .number_format((float) $reservation->total_amount, 0, ',', ' ').' FCFA). Départ prévu le '
                .$reservation->check_out->format('d/m/Y à H\hi').'. '
                .RoomAssignment::paymentSummary($reservation));
    }

    /**
     * The client's details are optional at the desk: whatever was typed is kept; with no name at
     * all the file is "Client de passage" (one guest record per stay, to complete later).
     *
     * @param  array<string, mixed>  $data
     */
    private function createGuest(array $data): Guest
    {
        $first = trim((string) ($data['new_first_name'] ?? ''));
        $last = trim((string) ($data['new_last_name'] ?? ''));

        return Guest::create([
            'first_name' => $first === '' && $last === '' ? Guest::WALK_IN_FIRST_NAME : $first,
            'last_name' => $first === '' && $last === '' ? Guest::WALK_IN_LAST_NAME : $last,
            'phone' => trim((string) ($data['new_phone'] ?? '')),
        ]);
    }
}
