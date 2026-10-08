<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomTypeRequest extends FormRequest
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
        $roomType = $this->route('room_type');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('room_types', 'name')->ignore($roomType)],
            'description' => ['nullable', 'string'],
            // Every rate is optional: an empty rate means that formula isn't offered for this type.
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'day_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'night_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'stay_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'capacity_adults' => ['required', 'integer', 'min:1'],
            'capacity_children' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'hourly_rate' => 'tarif à l\'heure',
            'day_rate' => 'tarif journée',
            'night_rate' => 'tarif nuitée',
            'stay_rate' => 'tarif séjour',
            'capacity_adults' => 'capacité adultes',
            'capacity_children' => 'capacité enfants',
        ];
    }
}
