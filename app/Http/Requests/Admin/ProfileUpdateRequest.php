<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A staff member editing their own name and login e-mail ("Mon profil"). Changing the e-mail
 * — the login identifier — needs the current password. Role and status stay with whoever
 * manages staff accounts (Personnel).
 */
class ProfileUpdateRequest extends FormRequest
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
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'confirm_password' => [Rule::requiredIf(fn () => $this->emailChanged()), 'nullable', 'string', 'current_password'],
        ];
    }

    public function emailChanged(): bool
    {
        return mb_strtolower(trim((string) $this->input('email'))) !== mb_strtolower((string) $this->user()->email);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nom', 'email' => 'e-mail', 'confirm_password' => 'mot de passe actuel'];
    }

    public function messages(): array
    {
        return ['confirm_password.required' => 'Pour changer votre e-mail de connexion, saisissez votre mot de passe actuel.'];
    }
}
