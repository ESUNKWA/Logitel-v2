<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Reservation;
use Illuminate\Validation\Validator;

/**
 * Optional discount ("remise") on an affectation or a réservation: discount_type (montant |
 * pourcentage), discount_value (FCFA or %), discount_reason. Blank value = no discount. The
 * model computes the actual amount (Reservation::discountFor()); these helpers let the form
 * requests check payments against the same discounted total.
 */
trait ValidatesDiscount
{
    /**
     * @return array<string, mixed>
     */
    protected function discountRules(): array
    {
        return [
            'discount_type' => ['nullable', 'in:'.Reservation::DISCOUNT_AMOUNT.','.Reservation::DISCOUNT_PERCENT],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Refuses a percentage above 100 or an amount above the subtotal (no negative total).
     */
    protected function checkDiscount(Validator $validator, string $subtotal): void
    {
        if ($validator->errors()->hasAny(['discount_type', 'discount_value'])) {
            return;
        }

        $value = (string) ($this->input('discount_value') ?: '0');
        if (bccomp($value, '0', 2) <= 0) {
            return;
        }

        if ($this->input('discount_type') === Reservation::DISCOUNT_PERCENT && bccomp($value, '100', 2) > 0) {
            $validator->errors()->add('discount_value', 'Une remise en pourcentage ne peut pas dépasser 100 %.');
        } elseif ($this->input('discount_type') !== Reservation::DISCOUNT_PERCENT && bccomp($value, $subtotal, 2) > 0) {
            $validator->errors()->add('discount_value', 'La remise ('.number_format((float) $value, 0, ',', ' ').' FCFA) dépasse le montant du séjour ('
                .number_format((float) $subtotal, 0, ',', ' ').' FCFA).');
        }
    }

    /**
     * The discount fields to store (a blank value clears the discount; amount is the default type).
     *
     * @return array{discount_type: ?string, discount_value: string, discount_reason: ?string}
     */
    public function discountAttributes(): array
    {
        $value = (string) ($this->input('discount_value') ?: '0');
        $none = bccomp($value, '0', 2) <= 0;

        return [
            'discount_type' => $none ? null : ($this->input('discount_type') === Reservation::DISCOUNT_PERCENT ? Reservation::DISCOUNT_PERCENT : Reservation::DISCOUNT_AMOUNT),
            'discount_value' => $none ? '0' : $value,
            'discount_reason' => $none ? null : (filled($this->input('discount_reason')) ? trim((string) $this->input('discount_reason')) : null),
        ];
    }

    /**
     * Subtotal minus the requested discount — what the guest owes.
     */
    protected function totalAfterDiscount(string $subtotal): string
    {
        $discount = $this->discountAttributes();

        return bcsub($subtotal, Reservation::discountFor($subtotal, $discount['discount_type'], $discount['discount_value']), 2);
    }

    /**
     * @return array<string, string>
     */
    protected function discountAttributeNames(): array
    {
        return ['discount_type' => 'type de remise', 'discount_value' => 'remise', 'discount_reason' => 'motif de la remise'];
    }
}
