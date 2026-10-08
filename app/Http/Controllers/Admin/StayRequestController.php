<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoomStatus;
use App\Enums\StayRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StayRequestApprovalRequest;
use App\Models\Guest;
use App\Models\StayRequest;
use App\Support\RoomAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Demandes clients": requests sent by guests from their phone (public page /demande). The
 * réception validates one into an affectation — cashing the stay in full, exactly like an
 * assignment from the room board (App\Support\RoomAssignment) — or refuses it.
 */
class StayRequestController extends Controller
{
    /**
     * Tab => [label, query constraint].
     */
    private function tabs(): array
    {
        return [
            'en-attente' => ['En attente', fn (Builder $q) => $q->holding()->orderBy('created_at')],
            'expirees' => ['Expirées', fn (Builder $q) => $q->expired()->latest()],
            'validees' => ['Validées', fn (Builder $q) => $q->where('status', StayRequestStatus::Validee)->latest('handled_at')],
            'refusees' => ['Refusées', fn (Builder $q) => $q->where('status', StayRequestStatus::Refusee)->latest('handled_at')],
        ];
    }

    public function index(Request $request): View
    {
        $tabs = $this->tabs();
        $tab = array_key_exists($request->string('onglet')->toString(), $tabs) ? $request->string('onglet')->toString() : 'en-attente';

        $requests = StayRequest::query()
            ->with(['room.roomType', 'handler', 'reservation'])
            ->tap($tabs[$tab][1])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.stay-requests.index', [
            'requests' => $requests,
            'tab' => $tab,
            'tabs' => collect($tabs)->map(fn (array $t) => $t[0]),
            'counts' => collect($tabs)->map(fn (array $t) => StayRequest::query()->tap($t[1])->count()),
        ]);
    }

    public function show(Request $request, StayRequest $stayRequest): View
    {
        $stayRequest->load(['room.roomType', 'reservation', 'handler']);

        // What would block a validation right now (the stay starts when it's validated).
        $roomProblem = null;
        if ($stayRequest->isPending() && $room = $stayRequest->room) {
            [$start, $end] = $stayRequest->pricing_mode->resolve(now()->second(0), $stayRequest->duration);
            $roomProblem = $room->status !== RoomStatus::Disponible
                ? "La chambre N°{$room->number} est « {$room->status->getLabel()} »."
                : $room->availabilityError($start, $end, null, $stayRequest->id);
        }

        return view('admin.stay-requests.show', [
            'stayRequest' => $stayRequest,
            'matchingGuest' => $stayRequest->isPending() ? $stayRequest->matchingGuest() : null,
            'roomProblem' => $roomProblem,
            'canHandle' => $stayRequest->isPending() && $request->user()->can('affectations.manage'),
            'canApprove' => $stayRequest->isPending() && $request->user()->can('affectations.manage') && $request->user()->can('payments.create'),
        ]);
    }

    public function approve(StayRequestApprovalRequest $request, StayRequest $stayRequest): RedirectResponse
    {
        if (! $stayRequest->isPending()) {
            return redirect()->route('admin.stay-requests.show', $stayRequest)->withErrors(['pricing_mode' => 'Cette demande a déjà été traitée.']);
        }

        $room = $stayRequest->room;
        [$start, $end] = $request->period();

        if ($room->status !== RoomStatus::Disponible) {
            return back()->withErrors(['pricing_mode' => "La chambre N°{$room->number} est « {$room->status->getLabel()} » : elle ne peut pas être affectée."])->withInput();
        }

        if ($error = $room->availabilityError($start, $end, null, $stayRequest->id)) {
            return back()->withErrors(['duration' => $error])->withInput();
        }

        $reservation = RoomAssignment::create($room, $request, function () use ($request, $stayRequest) {
            $existing = $request->boolean('new_guest') ? null : $stayRequest->matchingGuest();

            return $existing?->id ?? Guest::create([
                'first_name' => $stayRequest->first_name,
                'last_name' => $stayRequest->last_name,
                'phone' => $stayRequest->phone,
            ])->id;
        }, $stayRequest);

        return redirect()
            ->route('admin.affectations.show', $reservation)
            ->with('receipt', $reservation->id)
            ->with('status', "Demande N°{$stayRequest->id} validée : chambre N°{$room->number} affectée à {$reservation->guest->fullName()} ("
                .$reservation->billingLabel().' = '.number_format((float) $reservation->total_amount, 0, ',', ' ').' FCFA), départ le '
                .$reservation->check_out->format('d/m/Y à H\hi').'. '.RoomAssignment::paymentSummary($reservation));
    }

    public function refuse(Request $request, StayRequest $stayRequest): RedirectResponse
    {
        $data = $request->validate(['refusal_reason' => ['nullable', 'string', 'max:255']], [], ['refusal_reason' => 'motif']);

        if (! $stayRequest->isPending()) {
            return redirect()->route('admin.stay-requests.show', $stayRequest)->withErrors(['refusal_reason' => 'Cette demande a déjà été traitée.']);
        }

        // The room is released at once (only pending requests hold it).
        $stayRequest->forceFill([
            'status' => StayRequestStatus::Refusee,
            'refusal_reason' => $data['refusal_reason'] ?? null,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ])->save();

        return redirect()
            ->route('admin.stay-requests.index')
            ->with('status', "Demande N°{$stayRequest->id} refusée : la chambre N°{$stayRequest->room?->number} est de nouveau libre.");
    }

    /**
     * New-request feed, polled by public/js/admin.js on every admin page (staff who can handle them).
     */
    public function feed(): JsonResponse
    {
        $requests = StayRequest::query()
            ->holding()
            ->with('room.roomType')
            ->orderBy('created_at')
            ->get()
            ->map(fn (StayRequest $r) => [
                'id' => $r->id,
                'room' => $r->room?->number,
                'room_type' => $r->room?->roomType?->name,
                'guest' => $r->fullName(),
                'phone' => $r->phone,
                'mode' => $r->pricing_mode->getLabel(),
                'billing' => $r->billingLabel(),
                'total' => number_format((float) $r->total_amount, 0, ',', ' ').' FCFA',
                'sent_at' => $r->created_at->format('H\hi'),
                'expires_at' => $r->expires_at->format('H\hi'),
                'open_url' => route('admin.stay-requests.show', $r),
            ]);

        return response()->json($requests);
    }
}
