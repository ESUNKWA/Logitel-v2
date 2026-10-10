<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SiteEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // A bare web address gets https:// (same rule as the hotel information).
        $info = trim((string) $this->input('booking_info'));
        if (preg_match('/^www\./i', $info)) {
            $this->merge(['booking_info' => 'https://'.$info]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_label' => ['nullable', 'string', 'max:80'],
            // A link (http/https only — no javascript:…), a phone number or a short text.
            'booking_info' => ['nullable', 'string', 'max:255', 'not_regex:/^\s*(javascript|data|vbscript):/i'],
            'is_published' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=300,min_height=200,max_width=8000,max_height=8000'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function eventAttributes(): array
    {
        return [
            'title' => trim($this->validated('title')),
            'starts_at' => $this->validated('starts_at'),
            'ends_at' => $this->validated('ends_at'),
            'location' => $this->validated('location'),
            'description' => $this->validated('description'),
            'price_label' => $this->validated('price_label'),
            'booking_info' => $this->validated('booking_info'),
            'is_published' => $this->boolean('is_published'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'titre', 'starts_at' => 'début', 'ends_at' => 'fin', 'location' => 'lieu',
            'price_label' => 'prix', 'booking_info' => 'réservation / inscription', 'photo' => 'affiche',
        ];
    }

    public function messages(): array
    {
        return [
            'ends_at.after' => 'La fin doit être après le début.',
            'booking_info.not_regex' => 'Saisissez un lien web (https://…), un numéro de téléphone ou un court texte.',
        ];
    }
}
