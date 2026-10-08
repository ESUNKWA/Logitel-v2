<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Failed attempts allowed per e-mail + IP pair before a one-minute lockout.
     */
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            return back()
                ->withErrors(['email' => 'Trop de tentatives de connexion. Réessayez dans '.RateLimiter::availableIn($throttleKey).' secondes.'])
                ->onlyInput('email');
        }

        // Deactivated accounts get the same generic message as a wrong password (no account enumeration).
        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            return back()
                ->withErrors(['email' => 'Identifiants incorrects.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
