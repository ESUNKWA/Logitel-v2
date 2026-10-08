<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('role')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.users.index', [
            'users' => $users,
            'currentUser' => $request->user(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.users.create', [
            'user' => new User,
            'roles' => $this->grantableRoles($request->user()),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->role_id = $request->validated('role_id');
        $user->is_active = true;
        $user->must_change_password = true;
        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Compte créé. Le mot de passe provisoire devra être changé à la première connexion.');
    }

    public function edit(Request $request, User $user): View
    {
        abort_unless($request->user()->canManageUser($user), 403);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->grantableRoles($request->user()),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only(['name', 'email']));
        $user->role_id = $request->validated('role_id');
        $user->is_active = $request->boolean('is_active');

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
            $user->must_change_password = true;
        }

        $mustReauthenticate = $user->isDirty(['role_id', 'is_active', 'password', 'email']);
        $user->save();

        if ($mustReauthenticate) {
            $user->logoutEverywhere();
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Compte mis à jour.');
    }

    /**
     * Only roles whose permissions the acting user fully holds can be handed out.
     *
     * @return Collection<int, Role>
     */
    private function grantableRoles(User $actor): Collection
    {
        return Role::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role) => $actor->canGrantRole($role))
            ->values();
    }
}
