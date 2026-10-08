<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SettingsRequest extends FormRequest
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
            'business_day_start' => ['required', 'date_format:H:i'],
            'night_from' => ['required', 'date_format:H:i', 'different:night_until'],
            'night_until' => ['required', 'date_format:H:i'],
            'day_hours' => ['required', 'integer', 'min:1', 'max:23'],
            'stay_min_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'cleaning_buffer_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'request_expiry_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:240'],
            // Customer receipt (absent from older forms/tests = left unchanged)
            'receipt_width' => ['sometimes', 'required', 'integer', 'in:58,80'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $from = $this->input('night_from');
                $until = $this->input('night_until');

                // PricingMode assumes the night window spans midnight (evening → next morning).
                if (is_string($from) && is_string($until) && $from <= $until) {
                    $validator->errors()->add('night_from', 'La nuitée doit commencer le soir et se terminer le lendemain : l\'heure de début doit être après l\'heure de départ (ex. 22h00 → 12h00).');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'business_day_start' => 'début de la journée commerciale',
            'night_from' => 'début de la nuitée',
            'night_until' => 'fin de la nuitée',
            'day_hours' => 'durée de la journée',
            'stay_min_hours' => 'durée du séjour',
            'cleaning_buffer_minutes' => 'délai de ménage',
            'request_expiry_minutes' => 'délai de traitement des demandes',
            'receipt_width' => 'largeur du rouleau',
            'receipt_footer' => 'message de bas de reçu',
        ];
    }
}
