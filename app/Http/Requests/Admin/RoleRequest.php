<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RoleRequest extends FormRequest
{
    /**
     * A role can be edited/deleted only if it isn't a system role, isn't the actor's own role
     * (no self-escalation or self-lockout), and doesn't hold permissions the actor lacks.
     */
    public static function actorCanEdit(User $actor, Role $role): bool
    {
        return ! $role->is_system
            && (int) $actor->role_id !== $role->id
            && $actor->canGrantRole($role);
    }

    public function authorize(): bool
    {
        $role = $this->route('role');

        return ! $role instanceof Role || self::actorCanEdit($this->user(), $role);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($this->route('role'))],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permission::values())],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $permissions = $this->input('permissions', []);

                if (is_array($permissions) && ! $this->user()->canGrantPermissions(array_filter($permissions, 'is_string'))) {
                    $validator->errors()->add('permissions', "Vous ne pouvez pas accorder des permissions que vous n'avez pas vous-même.");
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
        ];
    }
}
