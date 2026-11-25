<?php

namespace App\Http\Requests\Admin;

use App\Enums\Department;
use App\Http\Requests\Admin\Concerns\ValidatesPaymentLines;
use App\Models\CatalogItem;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A sale of a service (restaurant, beach & pool): `items[catalog item id] = quantity`, an
 * optional label, discount (amount, never above the subtotal) and either an encaissement (all,
 * part or nothing — the rest stays due) or a charge to an in-progress stay (« report sur la
 * chambre », settled with the stay: no payment then). The total is always computed from the
 * catalogue here and in Sale::record() — never read from the form.
 */
class SaleRequest extends FormRequest
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
            'items' => ['required', 'array', 'max:200'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:'.Sale::MAX_QUANTITY],
            'label' => ['nullable', 'string', 'max:120'],
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            ...$this->paymentLineRules(required: false),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['items', 'items.*', 'discount_amount'])) {
                    return;
                }

                // Keys are catalogue ids: anything else (forged keys) is refused.
                $quantities = $this->quantities();
                if ($quantities === null) {
                    $validator->errors()->add('items', 'Prestations invalides.');

                    return;
                }
                if ($quantities === []) {
                    $validator->errors()->add('items', 'Choisissez au moins une prestation (quantité 1 ou plus).');

                    return;
                }

                $items = CatalogItem::query()->ofDepartment($this->department())->active()->whereIn('id', array_keys($quantities))->pluck('price', 'id');
                if ($items->count() !== count($quantities)) {
                    $validator->errors()->add('items', 'Une prestation choisie n\'est pas (ou plus) proposée par ce service.');

                    return;
                }

                $subtotal = '0';
                foreach ($quantities as $id => $quantity) {
                    $subtotal = bcadd($subtotal, bcmul((string) $items[$id], (string) $quantity, 2), 2);
                }
                $discount = $this->discount();
                if (bccomp($discount, $subtotal, 2) > 0) {
                    $validator->errors()->add('discount_amount', 'La remise ne peut pas dépasser le total de la vente ('.number_format((float) $subtotal, 0, ',', ' ').' FCFA).');

                    return;
                }

                if ((array) $this->input('payments', []) !== []) {
                    if ($this->filled('reservation_id')) {
                        $validator->errors()->add('payments', 'Une vente reportée sur la chambre se règle avec le séjour : n\'encaissez rien maintenant.');

                        return;
                    }
                    $this->checkNotAboveDue($validator, bcsub($subtotal, $discount, 2));
                    $this->checkCashReceivedEntered($validator);
                }
            },
        ];
    }

    /** The service of the page (from the route, never from the form). */
    public function department(): Department
    {
        return Department::fromSlug((string) $this->route('department')) ?? abort(404);
    }

    /**
     * Item id => quantity (> 0); null when a key isn't an id.
     *
     * @return array<int, int>|null
     */
    public function quantities(): ?array
    {
        $quantities = [];
        foreach ((array) $this->input('items', []) as $id => $quantity) {
            if (! is_int($id) && ! ctype_digit((string) $id)) {
                return null;
            }
            if ((int) $quantity > 0) {
                $quantities[(int) $id] = (int) $quantity;
            }
        }

        return $quantities;
    }

    public function discount(): string
    {
        return is_numeric($this->input('discount_amount')) ? bcadd((string) $this->input('discount_amount'), '0', 2) : '0';
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
        return [
            ...$this->paymentLineAttributes(),
            'items' => 'prestations',
            'items.*' => 'quantité',
            'label' => 'libellé',
            'reservation_id' => 'chambre',
            'discount_amount' => 'remise',
            'discount_reason' => 'motif de la remise',
        ];
    }
}
