<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable. `role_id`, `is_active` and `must_change_password`
     * are deliberately absent: they are only ever set explicitly after an authorization check.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(Permission|string $permission): bool
    {
        return $this->is_active && $this->role !== null && $this->role->grants($permission);
    }

    /**
     * @return list<string>
     */
    public function permissionValues(): array
    {
        return $this->is_active && $this->role !== null ? $this->role->permissionValues() : [];
    }

    /**
     * Privilege-escalation guard: a role (or a set of permissions) may only be handed out by
     * someone who already holds every permission it contains.
     *
     * @param  list<string>  $permissions
     */
    public function canGrantPermissions(array $permissions): bool
    {
        return array_diff($permissions, $this->permissionValues()) === [];
    }

    public function canGrantRole(Role $role): bool
    {
        return $this->canGrantPermissions($role->permissionValues());
    }

    /**
     * Staff can't administer their own account through the users module (role, status, password
     * reset) — that goes through the profile page — nor anyone whose role outranks theirs.
     */
    public function canManageUser(User $target): bool
    {
        return $target->isNot($this)
            && ($target->role === null || $this->canGrantRole($target->role));
    }

    /**
     * Ends every session of this user (except, optionally, the current one) and rotates the
     * "remember me" token so long-lived cookies stop working too.
     */
    public function logoutEverywhere(?string $exceptSessionId = null): void
    {
        $this->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $this->getKey())
                ->when($exceptSessionId, fn ($query, $id) => $query->where('id', '!=', $id))
                ->delete();
        }
    }

    public const UI_SIMPLE = 'simple';

    public const UI_FULL = 'complet';

    /**
     * Back-office layout chosen by this staff member ("Mode simple" by default).
     */
    public function usesSimpleUi(): bool
    {
        return $this->ui_mode !== self::UI_FULL;
    }
}
