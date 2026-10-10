<?php

namespace App\Support;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\StayRequestStatus;
use App\Http\Requests\Admin\RoomAssignmentRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\StayRequest;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates an "affectation" (the guest moves in now), all or nothing: lock the room and re-check
 * it, resolve the guest, create the stay and check it in (room → occupee and invoice via the
 * Reservation model events), then cash the payment lines. Shared by the room board
 * (RoomAssignmentController) and the validation of a guest's request (StayRequestController),
 * which also marks the request as validated in the same transaction.
 */
class RoomAssignment
{
    /**
     * @param  Closure(): int  $resolveGuestId  called inside the transaction, once the room is secured
     *
     * @throws ValidationException
     */
    public static function create(Room $room, RoomAssignmentRequest $request, Closure $resolveGuestId, ?StayRequest $stayRequest = null): Reservation
    {
        [$start, $end, $duration] = $request->period();
        $data = $request->validated();

        return DB::transaction(function () use ($room, $request, $resolveGuestId, $stayRequest, $start, $end, $duration, $data) {
            // A request is handled once: lock it so two receptionists can't both validate it.
            if ($stayRequest) {
                $stayRequest = StayRequest::query()->lockForUpdate()->findOrFail($stayRequest->id);
                if (! $stayRequest->isPending()) {
                    throw ValidationException::withMessages(['pricing_mode' => 'Cette demande a déjà été traitée ('.mb_strtolower($stayRequest->status->getLabel()).').']);
                }
            }

            // Serialise concurrent assignments of this room: lock it and re-check before writing.
            // The request being validated doesn't count against its own room.
            $locked = Room::lockAvailable($room->id, $start, $end, null, 'duration', $stayRequest?->id);
            if ($locked->status !== RoomStatus::Disponible) {
                throw ValidationException::withMessages(['pricing_mode' => "La chambre N°{$locked->number} est « {$locked->status->getLabel()} » : elle ne peut pas être affectée."]);
            }

            $reservation = Reservation::create([
                'guest_id' => $resolveGuestId(),
                'room_id' => $room->id,
                'pricing_mode' => $request->pricingMode(),
                'check_in' => $start,
                'duration' => $duration,
                'adults' => $data['adults'],
                'children' => $data['children'],
                'status' => ReservationStatus::Confirmee,
                'unit_rate' => $request->unitRate(), // the room type's rate, never the submitted one
                ...$request->discountAttributes(),
                'origin' => Reservation::ORIGIN_AFFECTATION,
            ]);

            // Same path as ReservationController@checkIn: the model event flips the room to occupee.
            $reservation->update(['status' => ReservationStatus::Enregistree]);

            // Encaissement at check-in, in the same transaction (all or nothing).
            if ($request->paymentLines() !== []) {
                $reservation->invoice->settle($request->paymentLines(), now());
            }

            if ($stayRequest) {
                $stayRequest->forceFill([
                    'status' => StayRequestStatus::Validee,
                    'reservation_id' => $reservation->id,
                    'handled_by' => $request->user()->id,
                    'handled_at' => now(),
                ])->save();
            }

            return $reservation;
        });
    }

    /**
     * "Encaissé : 25 000 FCFA (soldé). Monnaie à rendre : 5 000 FCFA."
     */
    public static function paymentSummary(Reservation $reservation): string
    {
        $invoice = $reservation->invoice->refresh();
        $paid = (float) $invoice->amountPaid();

        if ($paid <= 0) {
            return 'Aucun encaissement : reste dû '.number_format((float) $invoice->amount, 0, ',', ' ').' FCFA.';
        }

        $change = (float) $invoice->payments()->sum('change_given');

        return 'Encaissé : '.number_format($paid, 0, ',', ' ').' FCFA'
            .((float) $invoice->remainingAmount() > 0 ? ' — reste dû '.number_format((float) $invoice->remainingAmount(), 0, ',', ' ').' FCFA.' : ' (soldé).')
            .($change > 0 ? ' Monnaie à rendre : '.number_format($change, 0, ',', ' ').' FCFA.' : '');
    }
}
