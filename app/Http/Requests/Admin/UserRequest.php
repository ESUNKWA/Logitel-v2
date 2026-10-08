<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return ! $target instanceof User || $this->user()->canManageUser($target);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target)],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'is_active' => ['sometimes', 'boolean'],
            'password' => [$target ? 'nullable' : 'required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $roleId = $this->input('role_id');
                $role = is_scalar($roleId) ? Role::find($roleId) : null;

                if ($role && ! $this->user()->canGrantRole($role)) {
                    $validator->errors()->add('role_id', "Vous ne pouvez pas attribuer un rôle disposant de droits que vous n'avez pas.");
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
            'name' => 'nom',
            'role_id' => 'rôle',
            'password' => 'mot de passe',
        ];
    }
}
