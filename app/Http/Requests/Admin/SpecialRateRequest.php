<?php

namespace App\Http\Requests\Admin;

use App\Enums\PricingMode;
use App\Models\SpecialRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * A forfait (Configuration → Tarifs spéciaux): mode, duration, fixed price, room types, and
 * optionally days of the week and a period. The price field is `forfait_price` (grouped digits,
 * NormalizeMoneyInputs strips the spaces).
 */
class SpecialRateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'pricing_mode' => ['required', new Enum(PricingMode::class)],
            'duration' => ['required', 'integer', 'min:1', 'max:365'],
            'forfait_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'all_room_types' => ['boolean'],
            'room_type_ids' => ['exclude_if:all_room_types,1', 'required', 'array', 'min:1'],
            'room_type_ids.*' => ['integer', 'distinct', 'exists:room_types,id'],
            'days' => ['nullable', 'array'],
            'days.*' => ['integer', 'distinct', 'between:1,7'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * What the model stores (days: null when every day or none is ticked).
     *
     * @return array<string, mixed>
     */
    public function rateAttributes(): array
    {
        $days = collect($this->validated('days') ?? [])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();

        return [
            'name' => $this->validated('name'),
            'pricing_mode' => $this->validated('pricing_mode'),
            'duration' => (int) $this->validated('duration'),
            'price' => $this->validated('forfait_price'),
            'all_room_types' => $this->boolean('all_room_types'),
            'days' => $days === [] || count($days) === count(SpecialRate::DAYS) ? null : $days,
            'starts_on' => $this->validated('starts_on'),
            'ends_on' => $this->validated('ends_on'),
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
            'name' => 'nom',
            'pricing_mode' => 'mode',
            'duration' => 'durée',
            'forfait_price' => 'prix du forfait',
            'room_type_ids' => 'types de chambre',
            'days' => 'jours',
            'starts_on' => 'date de début',
            'ends_on' => 'date de fin',
        ];
    }

    public function messages(): array
    {
        return [
            'room_type_ids.required' => 'Choisissez au moins un type de chambre (ou « Tous les types »).',
            'ends_on.after_or_equal' => 'La date de fin doit être le même jour ou après la date de début.',
        ];
    }
}
