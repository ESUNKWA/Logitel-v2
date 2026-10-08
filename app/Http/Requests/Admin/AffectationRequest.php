<?php

namespace App\Http\Requests\Admin;

use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Updating a stay in progress: extend/shorten it (duration in its mode's unit), adjust the
 * negotiated rate, occupants and notes. The arrival and the mode don't change.
 */
class AffectationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'duration' => ['required', 'integer', 'min:1', 'max:365'],
            'unit_rate' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $stay = $this->route('affectation');

                if (! $stay instanceof Reservation || $validator->errors()->isNotEmpty()) {
                    return;
                }

                [, $end] = $stay->pricing_mode->resolve($stay->check_in, (int) $this->input('duration'));

                $error = Room::find($stay->room_id)?->availabilityError($stay->check_in, $end, $stay->id);
                if ($error) {
                    $validator->errors()->add('duration', 'Impossible de prolonger jusqu\'au '.$end->format('d/m/Y à H\hi').' : '.lcfirst($error));
                }

                $total = bcmul((string) $this->input('unit_rate'), (string) (int) $this->input('duration'), 2);
                $paid = $stay->invoice?->amountPaid() ?? '0';
                if (bccomp($total, $paid, 2) < 0) {
                    $validator->errors()->add('duration', 'Le nouveau montant ('.number_format((float) $total, 0, ',', ' ').' FCFA) serait inférieur à ce qui a déjà été encaissé ('
                        .number_format((float) $paid, 0, ',', ' ').' FCFA).');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['duration' => 'durée', 'unit_rate' => 'tarif'];
    }
}
