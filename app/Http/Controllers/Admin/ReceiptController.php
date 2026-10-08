<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customer receipt for an affectation or a réservation, laid out for a thermal printer
 * (58 or 80 mm roll, see Paramètres). Read-only: it only shows the file, its invoice and the
 * payments already recorded. Opened in a preview modal right after an affectation/réservation
 * (session flash `receipt`, see the admin layout) and from the "Imprimer le reçu" buttons.
 */
class ReceiptController extends Controller
{
    public function show(Request $request, Reservation $reservation): View
    {
        abort_if($reservation->invoice === null, 404);

        $reservation->load(['guest', 'room.roomType', 'creator', 'invoice.payments' => fn ($q) => $q->orderBy('paid_at')->orderBy('id')]);

        return view('admin.receipts.show', [
            'reservation' => $reservation,
            'invoice' => $reservation->invoice,
            'isStay' => $reservation->isStay(),
            'embedded' => $request->boolean('apercu'),
            'width' => (int) config('hotel.receipt.width') === 58 ? 58 : 80,
        ]);
    }
}
