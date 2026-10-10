<?php

namespace App\Http\Requests\Admin;

use App\Models\Guest;
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
            // A natural person or a legal entity; only the phone is required (client request).
            'type' => ['nullable', 'in:'.Guest::TYPE_PERSON.','.Guest::TYPE_COMPANY],
            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'id_document_type' => ['nullable', 'string', 'max:100'],
            'id_document_number' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Validated data ready to store: names never null (non-null columns), company fields only
     * kept for a legal entity.
     *
     * @return array<string, mixed>
     */
    public function guestAttributes(): array
    {
        $data = $this->validated();
        $company = ($data['type'] ?? null) === Guest::TYPE_COMPANY;

        return [
            ...$data,
            'type' => $company ? Guest::TYPE_COMPANY : Guest::TYPE_PERSON,
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'company_name' => $company ? ($data['company_name'] ?? null) : null,
            'tax_id' => $company ? ($data['tax_id'] ?? null) : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['company_name' => 'raison sociale', 'tax_id' => 'n° RCCM / NCC', 'first_name' => 'prénom', 'last_name' => 'nom', 'phone' => 'téléphone'];
    }
}
