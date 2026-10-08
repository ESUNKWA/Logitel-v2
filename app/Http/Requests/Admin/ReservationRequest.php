<?php

namespace App\Http\Requests\Admin;

use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Http\Requests\Admin\Concerns\ValidatesPaymentLines;
use App\Models\Reservation;
use App\Models\Room;
use App\Rules\RoomIsAvailableForDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * A réservation: a stay booked ahead, in any of the four pricing modes (arrival + duration;
 * a nuitée arrival is a date — the night starts at 22:00). Arrivals now or in the past are
 * affectations, made from the room board, not réservations. An optional deposit ("acompte")
 * can be taken when booking. The legacy date-based shape (check_in → check_out, as nuitées)
 * is still accepted: the duration is then inferred.
 */
class ReservationRequest extends FormRequest
{
    use ValidatesPaymentLines;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePaymentLines();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reservation = $this->route('reservation');

        return [
            'guest_id' => ['required', 'exists:guests,id'],
            'pricing_mode' => ['nullable', new Enum(PricingMode::class)],
            'check_in' => ['required', 'date'],
            'duration' => ['required_without:check_out', 'nullable', 'integer', 'min:1', 'max:365'],
            'check_out' => ['required_without:duration', 'nullable', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in([ReservationStatus::EnAttente->value, ReservationStatus::Confirmee->value])],
            'unit_rate' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'room_id' => ['required', 'exists:rooms,id', new RoomIsAvailableForDates($reservation?->id)],
            'notes' => ['nullable', 'string'],
            // Deposit (acompte) at booking only; later payments go through the invoice.
            ...$this->paymentLineRules(required: false),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['check_in', 'duration', 'check_out', 'unit_rate', 'room_id', 'pricing_mode'])) {
                    return;
                }

                $mode = $this->pricingMode();
                [$start, , $duration] = $this->period();
                $existing = $this->route('reservation');

                // A réservation is for later; booking (or moving) it into the past is refused.
                $startChanged = ! $existing instanceof Reservation
                    || ! $existing->check_in->equalTo($start) || $existing->pricing_mode !== $mode;
                if ($startChanged && $start->lessThanOrEqualTo(now())) {
                    $validator->errors()->add('check_in', 'Une réservation porte sur une arrivée à venir. Pour un client qui arrive maintenant, affectez la chambre depuis le tableau des chambres.');
                }

                $room = Room::with('roomType')->find($this->input('room_id'));
                if ($room && $room->status === RoomStatus::HorsService) {
                    $validator->errors()->add('room_id', "La chambre N°{$room->number} est hors service : elle ne peut pas être réservée.");
                }
                if ($room && $mode->rateFor($room->roomType) === null) {
                    $validator->errors()->add('pricing_mode', "Le mode « {$mode->getLabel()} » n'est pas proposé pour ce type de chambre (aucun tarif défini).");
                }

                $total = bcmul((string) $this->input('unit_rate'), (string) $duration, 2);

                if ($existing instanceof Reservation) {
                    $paid = $existing->invoice?->amountPaid() ?? '0';
                    if (bccomp($total, $paid, 2) < 0) {
                        $validator->errors()->add('duration', 'Le nouveau montant ('.number_format((float) $total, 0, ',', ' ').' FCFA) serait inférieur à l\'acompte déjà encaissé ('
                            .number_format((float) $paid, 0, ',', ' ').' FCFA).');
                    }
                } else {
                    // Deposit optional (none, partial or full), never above the total.
                    $this->checkNotAboveDue($validator, $total);
                    $this->checkCashReceivedEntered($validator);
                }
            },
        ];
    }

    public function pricingMode(): PricingMode
    {
        return PricingMode::tryFrom((string) $this->input('pricing_mode')) ?? PricingMode::Nuitee;
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: int} [start, end, duration]
     */
    public function period(): array
    {
        return $this->pricingMode()->resolve(
            Carbon::parse($this->input('check_in')),
            filled($this->input('duration')) ? (int) $this->input('duration') : null,
            filled($this->input('check_out')) ? Carbon::parse($this->input('check_out')) : null,
        );
    }

    /**
     * Validated attributes ready for Reservation::create()/update(): mode, normalised arrival
     * and duration (the model derives check_out and the amount). Deposit lines excluded.
     *
     * @return array<string, mixed>
     */
    public function reservationAttributes(): array
    {
        [$start, , $duration] = $this->period();

        $data = collect($this->validated())->except(['check_out', 'payments'])->all();

        return [...$data, 'pricing_mode' => $this->pricingMode(), 'check_in' => $start, 'duration' => $duration];
    }

    public function messages(): array
    {
        return $this->paymentLineMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->paymentLineAttributes(),
            'payments' => 'acompte',
            'duration' => 'durée',
            'unit_rate' => 'tarif',
            'check_in' => 'arrivée',
        ];
    }
}
