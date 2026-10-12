<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * End-of-stay alarm feed, polled by public/js/admin.js for staff who receive the alarm
 * (User::receivesCheckoutAlarm(): the Réception, not the Gérant). Every checked-in stay whose
 * departure time is reached rings continuously until it is checked out or extended; « Couper 5
 * minutes » only pauses it (for everyone), then it rings again.
 */
class CheckoutAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $canHandle = $request->user()->can('affectations.manage');

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
                'check_in' => $stay->check_in->format('d/m/Y H\\hi'),
                'check_out' => $stay->check_out->format('d/m/Y H\\hi'),
                'overdue_minutes' => (int) $stay->check_out->diffInMinutes(now()),
                'snoozed_until' => $stay->alarmSnoozedUntil()?->format('H\\hi'),
                'checkout_url' => $canHandle ? route('admin.affectations.show', $stay).'#check-out' : null,
                'extend_url' => $canHandle ? route('admin.affectations.show', $stay).'#prolonger' : null,
                'snooze_url' => route('admin.alerts.checkouts.snooze', $stay),
            ]);

        return response()->json($alerts);
    }

    /**
     * « Couper 5 minutes »: pauses this stay's alarm for everyone; it rings again afterwards
     * unless the stay has been checked out or extended in the meantime.
     */
    public function snooze(Request $request, Reservation $reservation): JsonResponse
    {
        $reservation->forceFill([
            'overdue_acknowledged_at' => now(),
            'overdue_acknowledged_by' => $request->user()->id,
        ])->saveQuietly();

        return response()->json(['snoozed_until' => $reservation->alarmSnoozedUntil()?->format('H\\hi')]);
    }
}
