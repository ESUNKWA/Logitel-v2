<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Configuration → Hôtel: identity and contact details. Field names are the setting keys
 * (App\Support\HotelSettings::KEYS), the logo is uploaded separately.
 */
class HotelInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // A bare "www.example.ci" is accepted and stored as https://…
        foreach (['hotel_website', 'hotel_facebook', 'hotel_instagram'] as $key) {
            $value = trim((string) $this->input($key));
            if ($value !== '' && ! preg_match('#^https?://#i', $value)) {
                $this->merge([$key => 'https://'.$value]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $phone = ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9][0-9 .\-\/()]{5,38}$/'];
        $url = ['nullable', 'string', 'max:200', 'url:http,https'];

        return [
            'hotel_name' => ['required', 'string', 'max:120'],
            'hotel_tagline' => ['nullable', 'string', 'max:160'],
            'hotel_phone' => ['required', ...array_slice($phone, 1)],
            'hotel_phone2' => $phone,
            'hotel_whatsapp' => $phone,
            'hotel_email' => ['nullable', 'string', 'max:120', 'email'],
            'hotel_website' => $url,
            'hotel_address' => ['nullable', 'string', 'max:200'],
            'hotel_city' => ['nullable', 'string', 'max:80'],
            'hotel_country' => ['nullable', 'string', 'max:80'],
            'hotel_rccm' => ['nullable', 'string', 'max:60'],
            'hotel_ncc' => ['nullable', 'string', 'max:60'],
            'hotel_tax_regime' => ['nullable', 'string', 'max:80'],
            'hotel_legal_other' => ['nullable', 'string', 'max:200'],
            'hotel_facebook' => $url,
            'hotel_instagram' => $url,
            // A real, decodable raster image (no SVG: it could carry scripts).
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=50,min_height=20,max_width=4000,max_height=4000'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'hotel_name' => 'nom de l\'hôtel',
            'hotel_tagline' => 'slogan',
            'hotel_phone' => 'téléphone principal',
            'hotel_phone2' => 'deuxième téléphone',
            'hotel_whatsapp' => 'WhatsApp',
            'hotel_email' => 'e-mail',
            'hotel_website' => 'site web',
            'hotel_address' => 'adresse',
            'hotel_city' => 'ville',
            'hotel_country' => 'pays',
            'hotel_rccm' => 'RCCM',
            'hotel_ncc' => 'NCC',
            'hotel_tax_regime' => 'régime fiscal',
            'hotel_legal_other' => 'autres mentions',
            'hotel_facebook' => 'page Facebook',
            'hotel_instagram' => 'compte Instagram',
            'logo' => 'logo',
        ];
    }

    public function messages(): array
    {
        return [
            'hotel_phone.regex' => 'Saisissez un numéro valide (chiffres, espaces, + au début), ex. +225 07 00 00 00 00.',
            'hotel_phone2.regex' => 'Saisissez un numéro valide (chiffres, espaces, + au début), ex. +225 07 00 00 00 00.',
            'hotel_whatsapp.regex' => 'Saisissez un numéro valide (chiffres, espaces, + au début), ex. +225 07 00 00 00 00.',
        ];
    }
}
