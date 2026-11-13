<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesPaymentLines;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\SpecialRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Extending a stay in progress at the guest's request, whenever it is asked (client request,
 * 2026-10-25): `extra` more units of the stay's own formula (the mode never changes), and an
 * optional encaissement of the supplement — it may also stay due and be paid later.
 * The new departure and total are derived by the model, as for any duration change.
 */
class AffectationExtensionRequest extends FormRequest
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
        return [
            'extra' => ['required', 'integer', 'min:1', 'max:365'],
            ...$this->paymentLineRules(required: false),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $stay = $this->route('affectation');

                if (! $stay instanceof Reservation || $validator->errors()->has('extra')) {
                    return;
                }

                $duration = $this->newDuration();
                if ($duration > 365) {
                    $validator->errors()->add('extra', 'La durée totale ne peut pas dépasser 365 '.$stay->pricing_mode->unitPlural().'.');

                    return;
                }

                [, $end] = $stay->pricing_mode->resolve($stay->check_in, $duration, null, $stay->stay_until);
                $error = Room::find($stay->room_id)?->availabilityError($stay->check_in, $end, $stay->id);
                if ($error) {
                    $validator->errors()->add('extra', 'Impossible de prolonger jusqu\'au '.$end->format('d/m/Y à H\hi').' : '.lcfirst($error));

                    return;
                }

                // Same total as the model will compute: forfait of the new duration on the entry
                // day (affectations only), else rate × duration, minus the stay's discount.
                $total = $this->newTotal($stay, $duration);
                $paid = $stay->invoice?->amountPaid() ?? '0';
                if (bccomp($total, $paid, 2) < 0) {
                    $validator->errors()->add('extra', 'Le nouveau montant ('.number_format((float) $total, 0, ',', ' ').' FCFA) serait inférieur à ce qui a déjà été encaissé ('
                        .number_format((float) $paid, 0, ',', ' ').' FCFA).');

                    return;
                }

                if ((array) $this->input('payments', []) !== []) {
                    $this->checkNotAboveDue($validator, bcsub($total, $paid, 2));
                    $this->checkCashReceivedEntered($validator);
                }
            },
        ];
    }

    /** Read from the input (already checked as an integer): also used inside after(). */
    public function newDuration(): int
    {
        return (int) $this->route('affectation')->duration + (int) $this->input('extra');
    }

    private function newTotal(Reservation $stay, int $duration): string
    {
        $special = $stay->origin === Reservation::ORIGIN_AFFECTATION
            ? SpecialRate::best($stay->room?->room_type_id, $stay->pricing_mode, $duration, $stay->check_in, (string) $stay->unit_rate)
            : null;
        $subtotal = $special ? bcadd((string) $special->price, '0', 2) : bcmul((string) $stay->unit_rate, (string) $duration, 2);

        return bcsub($subtotal, Reservation::discountFor($subtotal, $stay->discount_type, (string) ($stay->discount_value ?? '0')), 2);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->paymentLineMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['extra' => 'prolongation', ...$this->paymentLineAttributes()];
    }
}
