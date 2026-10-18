<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PasswordUpdateRequest;
use App\Http\Requests\Admin\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.profile.edit', [
            'user' => $request->user()->load('role'),
        ]);
    }

    /**
     * Own name and login identifiers (e-mail and/or phone). A new identifier closes the
     * account's other sessions (like a new password), since it's used to sign in.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $changed = $request->identifiersChanged();

        // Only what was sent: a form without « phone » never erases the number.
        $user->fill($request->safe()->only(['name', 'email', 'phone']))->save();

        if ($changed) {
            $request->session()->regenerate();
            $user->logoutEverywhere(exceptSessionId: $request->session()->getId());
        }

        $identifiers = array_filter([$user->email, $user->phone]);

        return redirect()
            ->route('admin.profile.edit')
            ->with('status', $changed
                ? 'Informations enregistrées. Vous vous connectez désormais avec '.implode(' ou ', $identifiers).' ; vos autres sessions ont été fermées.'
                : 'Informations enregistrées.');
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->password = $request->validated('password');
        $user->must_change_password = false;
        $user->save();

        $request->session()->regenerate();
        $user->logoutEverywhere(exceptSessionId: $request->session()->getId());

        return redirect()
            ->route('admin.profile.edit')
            ->with('status', 'Mot de passe modifié. Vos autres sessions ont été fermées.');
    }
}
