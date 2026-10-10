<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * « Demandes en ligne »: booking requests sent from the website (« Réserver »). The réception
 * calls the guest back, then creates the réservation from the usual form, pre-filled
 * (ReservationController@create with ?demande=ID, which marks the request « traitée » on save),
 * or refuses it.
 */
class BookingRequestController extends Controller
{
    /**
     * Tab => [label, query constraint].
     */
    private function tabs(): array
    {
        return [
            'en-attente' => ['En attente', fn (Builder $q) => $q->pending()->orderBy('check_in')],
            'traitees' => ['Réservations créées', fn (Builder $q) => $q->where('status', BookingRequest::STATUS_HANDLED)->latest('handled_at')],
            'refusees' => ['Refusées', fn (Builder $q) => $q->where('status', BookingRequest::STATUS_REFUSED)->latest('handled_at')],
        ];
    }

    public function index(Request $request): View
    {
        $tabs = $this->tabs();
        $tab = array_key_exists($request->string('onglet')->toString(), $tabs) ? $request->string('onglet')->toString() : 'en-attente';

        return view('admin.booking-requests.index', [
            'requests' => BookingRequest::query()->with(['roomType', 'handler', 'reservation'])->tap($tabs[$tab][1])->orderByDesc('id')->paginate(20)->withQueryString(),
            'tab' => $tab,
            'tabs' => collect($tabs)->map(fn (array $t) => $t[0]),
            'counts' => collect($tabs)->map(fn (array $t) => BookingRequest::query()->tap($t[1])->count()),
        ]);
    }

    public function refuse(Request $request, BookingRequest $bookingRequest): RedirectResponse
    {
        $data = $request->validate(['refusal_reason' => ['nullable', 'string', 'max:500']]);

        $refused = DB::transaction(function () use ($bookingRequest, $data, $request) {
            $locked = BookingRequest::query()->lockForUpdate()->findOrFail($bookingRequest->id);
            if (! $locked->isPending()) {
                return false;
            }
            $locked->forceFill([
                'status' => BookingRequest::STATUS_REFUSED,
                'refusal_reason' => trim((string) ($data['refusal_reason'] ?? '')) ?: null,
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ])->save();

            return true;
        });

        return $refused
            ? redirect()->route('admin.booking-requests.index')->with('status', "Demande n°{$bookingRequest->id} refusée.")
            : redirect()->route('admin.booking-requests.index')->withErrors(['refusal_reason' => 'Cette demande a déjà été traitée.']);
    }
}
