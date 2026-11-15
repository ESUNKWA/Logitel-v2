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

    protected function prepareForValidation(): void
    {
        // A bare "uat.example.ci" is accepted and stored as https://…
        // Only a plain string is normalised: a forged array is left to the `string` rule (422, not 500).
        if (is_string($this->input('training_url'))) {
            $value = trim($this->input('training_url'));
            if ($value !== '' && ! preg_match('#^https?://#i', $value)) {
                $this->merge(['training_url' => 'https://'.$value]);
            }
        }
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
            // Absent from older forms/tests = left unchanged.
            'stay_checkout_time' => ['sometimes', 'required', 'date_format:H:i'],
            'cleaning_buffer_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'request_expiry_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:240'],
            // Customer receipt (absent from older forms/tests = left unchanged)
            'receipt_width' => ['sometimes', 'required', 'integer', 'in:58,80'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:200'],
            // « Formation » menu entry (absent = unchanged, empty = no entry)
            'training_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url:http,https'],
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

                // « Formation » link: shown to every staff member, so no credentials inside it
                // (https://user:password@…) and nothing but a plain web address.
                $training = $this->input('training_url');
                if (is_string($training) && $training !== '' && ! $validator->errors()->has('training_url')) {
                    $parts = parse_url($training);
                    if ($parts === false || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                        || preg_match('/[\s\x00-\x1F\x7F<>"\'`\\\\]/', $training)) {
                        $validator->errors()->add('training_url', 'Saisissez une adresse web simple, sans identifiant ni mot de passe, par exemple https://uat.exemple.ci/admin.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'training_url.url' => 'Saisissez une adresse web valide, par exemple https://uat.exemple.ci/admin.',
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
            'stay_checkout_time' => 'heure de fin du séjour',
            'cleaning_buffer_minutes' => 'délai de ménage',
            'request_expiry_minutes' => 'délai de traitement des demandes',
            'receipt_width' => 'largeur du rouleau',
            'receipt_footer' => 'message de bas de reçu',
            'training_url' => 'adresse de l\'environnement de formation',
        ];
    }
}
