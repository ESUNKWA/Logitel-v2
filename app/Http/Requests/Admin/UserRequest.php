<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesLoginIdentifiers;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    use ValidatesLoginIdentifiers;

    public function authorize(): bool
    {
        $target = $this->route('user');

        return ! $target instanceof User || $this->user()->canManageUser($target);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareLoginIdentifiers();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            ...$this->loginIdentifierRules($target instanceof User ? $target : null),
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'is_active' => ['sometimes', 'boolean'],
            'password' => [$target ? 'nullable' : 'required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->route('user');
                $this->checkOneLoginIdentifier($validator, $target instanceof User ? $target : null);

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
            'phone' => 'téléphone',
            'role_id' => 'rôle',
            'password' => 'mot de passe',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->loginIdentifierMessages();
    }
}
