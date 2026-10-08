<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GuestRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'id_document_type' => ['nullable', 'string', 'max:100'],
            'id_document_number' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
