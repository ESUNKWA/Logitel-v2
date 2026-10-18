<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesLoginIdentifiers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A staff member editing their own name and login identifiers ("Mon profil": e-mail and/or
 * phone, at least one). Changing either — they're used to sign in — needs the current password. Role and status stay with whoever
 * manages staff accounts (Personnel).
 */
class ProfileUpdateRequest extends FormRequest
{
    use ValidatesLoginIdentifiers;

    protected function prepareForValidation(): void
    {
        $this->prepareLoginIdentifiers();
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            ...$this->loginIdentifierRules($user),
            'confirm_password' => [Rule::requiredIf(fn () => $this->identifiersChanged()), 'nullable', 'string', 'current_password'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkOneLoginIdentifier($validator, $this->user())];
    }

    public function emailChanged(): bool
    {
        return $this->has('email') && mb_strtolower((string) $this->input('email')) !== mb_strtolower((string) $this->user()->email);
    }

    public function phoneChanged(): bool
    {
        return $this->has('phone') && $this->input('phone') !== $this->user()->phone;
    }

    /**
     * The e-mail or the phone changes: both sign in, so the current password is required.
     */
    public function identifiersChanged(): bool
    {
        return $this->emailChanged() || $this->phoneChanged();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nom', 'email' => 'e-mail', 'phone' => 'téléphone', 'confirm_password' => 'mot de passe actuel'];
    }

    public function messages(): array
    {
        return [
            ...$this->loginIdentifierMessages(),
            'confirm_password.required' => 'Pour changer votre e-mail ou votre téléphone de connexion, saisissez votre mot de passe actuel.',
        ];
    }
}
