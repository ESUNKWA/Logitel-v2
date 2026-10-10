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
     * Own name and login e-mail. A new e-mail closes the account's other sessions (like a new
     * password), since it's the login identifier.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $emailChanged = $request->emailChanged();

        $user->fill(['name' => $request->validated('name'), 'email' => trim($request->validated('email'))])->save();

        if ($emailChanged) {
            $request->session()->regenerate();
            $user->logoutEverywhere(exceptSessionId: $request->session()->getId());
        }

        return redirect()
            ->route('admin.profile.edit')
            ->with('status', $emailChanged
                ? 'Informations enregistrées. Votre nouvel e-mail de connexion est '.$user->email.' ; vos autres sessions ont été fermées.'
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
