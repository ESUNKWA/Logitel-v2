<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesPaymentLines;
use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A réservation's guest arrives (« Arrivée du client »): the check-in, with an optional
 * encaissement of what is left after the deposit — all, part or nothing (the rest may stay due).
 * Never above what is still due; Invoice::settle() re-checks it under a row lock.
 */
class CheckInRequest extends FormRequest
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
        return $this->paymentLineRules(required: false);
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $reservation = $this->route('reservation');
                if (! $reservation instanceof Reservation || (array) $this->input('payments', []) === []) {
                    return;
                }

                $this->checkNotAboveDue($validator, $reservation->invoice?->remainingAmount() ?? '0');
                $this->checkCashReceivedEntered($validator);
            },
        ];
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
        return $this->paymentLineAttributes();
    }
}
