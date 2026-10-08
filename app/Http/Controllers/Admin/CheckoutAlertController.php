<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Checkout alarm feed, polled by public/js/admin.js on every admin page for staff holding the
 * `alerts.checkout` permission: checked-in stays whose departure time has been reached.
 */
class CheckoutAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $canOpen = $request->user()->can('affectations.manage');

        $alerts = Reservation::query()
            ->overdueCheckouts()
            ->with(['room.roomType', 'guest'])
            ->orderBy('check_out')
            ->get()
            ->map(fn (Reservation $stay) => [
                'id' => $stay->id,
                'room' => $stay->room?->number,
                'room_type' => $stay->room?->roomType?->name,
                'guest' => $stay->guest?->fullName(),
                'phone' => $stay->guest?->phone,
                'mode' => $stay->pricing_mode->getLabel(),
                'billing' => $stay->billingLabel(),
                'check_in' => $stay->check_in->format('d/m/Y H\hi'),
                'check_out' => $stay->check_out->format('d/m/Y H\hi'),
                'overdue_minutes' => (int) $stay->check_out->diffInMinutes(now()),
                'open_url' => $canOpen ? route('admin.affectations.show', $stay) : null,
                'acknowledge_url' => route('admin.alerts.checkouts.acknowledge', $stay),
            ]);

        return response()->json($alerts);
    }

    /**
     * "Vu": silences the alarm for this stay (for every recipient) until its departure changes.
     */
    public function acknowledge(Request $request, Reservation $reservation): JsonResponse
    {
        $reservation->forceFill([
            'overdue_acknowledged_at' => now(),
            'overdue_acknowledged_by' => $request->user()->id,
        ])->saveQuietly();

        return response()->json(['ok' => true]);
    }
}
