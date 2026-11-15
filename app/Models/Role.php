<?php

namespace App\Models;

use App\Enums\Permission;
use App\Models\Concerns\Audited;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Role extends Model
{
    use Audited;

    public function auditFields(): array
    {
        return ['name' => 'Nom', 'description' => 'Description'];
    }

    public function auditKey(): string
    {
        return 'role';
    }

    public function auditCategory(): string
    {
        return 'personnel';
    }

    public function auditName(): string
    {
        return 'rôle « '.$this->name.' »';
    }

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
        $before = DB::table('permission_role')->where('role_id', $this->id)->pluck('permission')->all();
        $after = array_values(array_unique($permissions));
        $label = fn (string $p) => Permission::tryFrom($p)?->getLabel() ?? $p;
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        DB::transaction(function () use ($permissions) {
            DB::table('permission_role')->where('role_id', $this->id)->delete();

            DB::table('permission_role')->insert(array_map(
                fn (string $permission) => ['role_id' => $this->id, 'permission' => $permission],
                array_values(array_unique($permissions)),
            ));
        });

        $this->permissionCache = null;

        if ($added !== [] || $removed !== []) {
            Audit::record('role.permissions', 'personnel', 'Permissions modifiées : rôle « '.$this->name.' »', 'role', (int) $this->id, null, array_merge(
                array_map(fn ($p) => ['label' => 'Ajoutée', 'old' => null, 'new' => $label($p)], $added),
                array_map(fn ($p) => ['label' => 'Retirée', 'old' => $label($p), 'new' => null], $removed),
            ));
        }
    }
}
