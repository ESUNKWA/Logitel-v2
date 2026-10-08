<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Role extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * @var list<string>|null
     */
    protected ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Role $role) {
            if (blank($role->slug)) {
                $base = Str::slug($role->name) ?: 'role';
                $slug = $base;

                for ($i = 2; static::query()->where('slug', $slug)->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }

                $role->slug = $slug;
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Permission values granted by this role. A system role implicitly holds every permission,
     * including ones added to the enum later, so it never needs re-syncing.
     *
     * @return list<string>
     */
    public function permissionValues(): array
    {
        if ($this->is_system) {
            return Permission::values();
        }

        return $this->permissionCache ??= DB::table('permission_role')
            ->where('role_id', $this->id)
            ->pluck('permission')
            ->intersect(Permission::values())
            ->values()
            ->all();
    }

    public function grants(Permission|string $permission): bool
    {
        $value = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($value, $this->permissionValues(), true);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        DB::transaction(function () use ($permissions) {
            DB::table('permission_role')->where('role_id', $this->id)->delete();

            DB::table('permission_role')->insert(array_map(
                fn (string $permission) => ['role_id' => $this->id, 'permission' => $permission],
                array_values(array_unique($permissions)),
            ));
        });

        $this->permissionCache = null;
    }
}
