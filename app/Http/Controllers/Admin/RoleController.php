<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->withCount('users')->orderByDesc('is_system')->orderBy('name')->get(),
            'currentUser' => $request->user(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.roles.create', [
            'role' => new Role,
            'groupedPermissions' => Permission::grouped(),
            'grantable' => $request->user()->permissionValues(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $role = Role::create($request->safe()->only(['name', 'description']));
            $role->syncPermissions($request->validated('permissions', []));
        });

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Rôle créé.');
    }

    public function edit(Request $request, Role $role): View
    {
        abort_unless(RoleRequest::actorCanEdit($request->user(), $role), 403);

        return view('admin.roles.edit', [
            'role' => $role,
            'groupedPermissions' => Permission::grouped(),
            'grantable' => $request->user()->permissionValues(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        DB::transaction(function () use ($request, $role) {
            $role->update($request->safe()->only(['name', 'description']));
            $role->syncPermissions($request->validated('permissions', []));
        });

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Rôle mis à jour.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_unless(RoleRequest::actorCanEdit($request->user(), $role), 403);

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Ce rôle est attribué à des membres du personnel : réattribuez-les avant de le supprimer.']);
        }

        $role->delete();

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Rôle supprimé.');
    }
}
