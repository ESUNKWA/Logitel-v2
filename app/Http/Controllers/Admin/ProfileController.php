<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PasswordUpdateRequest;
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
