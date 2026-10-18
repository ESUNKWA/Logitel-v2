<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A staff account's login identifiers (client request, 2026-10-18): an e-mail and/or a phone
 * number — at least one —, each unique among staff. The phone is exactly 10 digits, no
 * international prefix; spaces, dots and dashes typed in it are dropped (User::normalizePhone()).
 * Shared by the staff form (UserRequest) and « Mon profil » (ProfileUpdateRequest).
 */
trait ValidatesLoginIdentifiers
{
    /**
     * Normalises the identifiers that were sent (a field left out of the request is kept as is
     * by the controllers — an older form without « phone » never erases a number).
     */
    protected function prepareLoginIdentifiers(): void
    {
        if ($this->has('email')) {
            $email = Str::lower(trim((string) (is_string($this->input('email')) ? $this->input('email') : '')));
            $this->merge(['email' => $email === '' ? null : $email]);
        }
        if ($this->has('phone')) {
            $this->merge(['phone' => User::normalizePhone(is_string($this->input('phone')) ? $this->input('phone') : null)]);
        }
    }

    /**
     * The identifiers the account will have once saved: what was sent, else what it has.
     *
     * @return array{email: string|null, phone: string|null}
     */
    public function loginIdentifiers(?User $current): array
    {
        return [
            'email' => $this->has('email') ? $this->input('email') : $current?->email,
            'phone' => $this->has('phone') ? $this->input('phone') : $current?->phone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function loginIdentifierRules(?User $ignore): array
    {
        return [
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignore)],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{10}$/', Rule::unique('users', 'phone')->ignore($ignore)],
        ];
    }

    protected function checkOneLoginIdentifier(Validator $validator, ?User $current): void
    {
        $identifiers = $this->loginIdentifiers($current);
        if ($identifiers['email'] === null && $identifiers['phone'] === null) {
            $validator->errors()->add('email', 'Renseignez au moins un e-mail ou un numéro de téléphone : il sert à se connecter.');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function loginIdentifierMessages(): array
    {
        return [
            'phone.regex' => 'Le numéro de téléphone doit comporter exactement 10 chiffres, sans indicatif international (ex. 07 12 34 56 78).',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé par un autre compte du personnel.',
            'email.unique' => 'Cet e-mail est déjà utilisé par un autre compte du personnel.',
        ];
    }
}
