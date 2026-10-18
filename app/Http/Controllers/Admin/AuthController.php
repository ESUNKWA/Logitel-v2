<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        // One field, « E-mail ou téléphone » (still named `email`): with an « @ » it's the
        // e-mail, otherwise the 10-digit phone (spaces, dots, dashes ignored).
        $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], ['email.required' => 'Saisissez votre e-mail ou votre numéro de téléphone.']);

        $identifier = trim($request->string('email')->toString());
        $byEmail = str_contains($identifier, '@');
        $phone = $byEmail ? null : User::normalizePhone($identifier);
        $credentials = $byEmail
            ? ['email' => $identifier, 'password' => $request->string('password')->toString()]
            : ['phone' => $phone, 'password' => $request->string('password')->toString()];

        $throttleKey = Str::transliterate(Str::lower($byEmail ? $identifier : (string) $phone).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            return back()
                ->withErrors(['email' => 'Trop de tentatives de connexion. Réessayez dans '.RateLimiter::availableIn($throttleKey).' secondes.'])
                ->onlyInput('email');
        }

        // Deactivated accounts — and anything that is neither an e-mail nor a 10-digit number —
        // get the same generic message as a wrong password (no account enumeration).
        $wellFormed = $byEmail || preg_match('/^[0-9]{10}$/', (string) $phone) === 1;
        if (! $wellFormed || ! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
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
