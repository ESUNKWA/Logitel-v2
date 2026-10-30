<?php

namespace App\Http\Requests\Admin;

use App\Enums\PricingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An included perk (Configuration → Avantages inclus): the text printed on the receipt, the room
 * types and the formulas it's granted for.
 */
class PerkRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:120'],
            'all_room_types' => ['boolean'],
            'room_type_ids' => ['exclude_if:all_room_types,1', 'required', 'array', 'min:1'],
            'room_type_ids.*' => ['integer', 'distinct', 'exists:room_types,id'],
            'modes' => ['nullable', 'array'],
            'modes.*' => ['string', 'distinct', Rule::in(array_column(PricingMode::cases(), 'value'))],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * What the model stores (modes: null when none or all are ticked = every formula).
     *
     * @return array<string, mixed>
     */
    public function perkAttributes(): array
    {
        $modes = collect(PricingMode::cases())
            ->map->value
            ->filter(fn (string $mode) => in_array($mode, $this->validated('modes') ?? [], true))
            ->values()
            ->all();

        return [
            'label' => trim($this->validated('label')),
            'all_room_types' => $this->boolean('all_room_types'),
            'modes' => $modes === [] || count($modes) === count(PricingMode::cases()) ? null : $modes,
            'position' => (int) ($this->validated('position') ?? 0),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /**
     * @return list<int>
     */
    public function roomTypeIds(): array
    {
        return $this->boolean('all_room_types') ? [] : array_map('intval', $this->validated('room_type_ids') ?? []);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => 'texte de l\'avantage',
            'room_type_ids' => 'types de chambre',
            'modes' => 'formules',
            'position' => 'ordre',
        ];
    }

    public function messages(): array
    {
        return [
            'room_type_ids.required' => 'Choisissez au moins un type de chambre (ou « Tous les types »).',
        ];
    }
}
