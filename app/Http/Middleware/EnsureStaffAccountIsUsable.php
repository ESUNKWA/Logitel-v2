<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every authenticated admin request: kicks out accounts deactivated since they logged
 * in, and confines accounts with a temporary password to the profile page until they change it.
 */
class EnsureStaffAccountIsUsable
{
    /**
     * Routes still reachable while a password change is pending.
     */
    private const PASSWORD_CHANGE_ROUTES = [
        'admin.profile.edit',
        'admin.profile.password.update',
        'admin.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors(['email' => 'Votre compte est désactivé. Contactez un responsable.']);
        }

        if ($user->must_change_password && ! $request->routeIs(self::PASSWORD_CHANGE_ROUTES)) {
            return redirect()
                ->route('admin.profile.edit')
                ->with('status', 'Veuillez choisir un nouveau mot de passe avant de continuer.');
        }

        return $next($request);
    }
}
