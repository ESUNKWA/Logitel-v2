<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesDiscount;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\SpecialRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Updating a stay in progress: extend/shorten it (duration in its mode's unit), set or change
 * its discount, occupants and notes. The arrival, the mode and the rate (the one billed at
 * check-in) don't change.
 */
class AffectationRequest extends FormRequest
{
    use ValidatesDiscount;

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
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            ...$this->discountRules(),
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

                // Same price as the model will compute: the forfait for the new duration (on the
                // stay's entry day; affectations only), else rate × duration.
                $duration = (int) $this->input('duration');
                $special = $stay->origin === Reservation::ORIGIN_AFFECTATION
                    ? SpecialRate::best($stay->room?->room_type_id, $stay->pricing_mode, $duration, $stay->check_in, (string) $stay->unit_rate)
                    : null;
                $subtotal = $special ? bcadd((string) $special->price, '0', 2) : bcmul((string) $stay->unit_rate, (string) $duration, 2);
                $this->checkDiscount($validator, $subtotal);
                $total = $this->totalAfterDiscount($subtotal);
                $paid = $stay->invoice?->amountPaid() ?? '0';
                if (bccomp($total, $paid, 2) < 0) {
                    $validator->errors()->add('duration', 'Le nouveau montant ('.number_format((float) $total, 0, ',', ' ').' FCFA) serait inférieur à ce qui a déjà été encaissé ('
                        .number_format((float) $paid, 0, ',', ' ').' FCFA).');
                }
            },
        ];
    }

    /**
     * What AffectationController@update stores (the rate is never taken from input).
     *
     * @return array<string, mixed>
     */
    public function stayAttributes(): array
    {
        return [
            ...collect($this->validated())->except(['discount_type', 'discount_value', 'discount_reason'])->all(),
            ...$this->discountAttributes(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['duration' => 'durée', 'unit_rate' => 'tarif', ...$this->discountAttributeNames()];
    }
}
