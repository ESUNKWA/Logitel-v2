<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesPaymentLines;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One "encaissement" on an invoice: one or more lines (mixed payment), never above what is due.
 */
class PaymentRequest extends FormRequest
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
            ...$this->paymentLineRules(required: true),
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $invoice = $this->route('invoice');

                if ($invoice instanceof Invoice) {
                    $this->checkNotAboveDue($validator, $invoice->remainingAmount());
                }
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
        return [...$this->paymentLineAttributes(), 'paid_at' => 'date du paiement'];
    }
}
