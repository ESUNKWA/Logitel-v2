<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\DutyController;
use App\Models\DutyShift;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * « Prise de service » by periods (client request, 2026-11-25), for a receptionist
 * (User::takesDuty()) opening a page:
 * - nobody on duty → she takes the service automatically (no click), and works;
 * - she is on duty → nothing;
 * - a colleague is on duty → the relief page asks « Prendre la relève ? » (DutyController@show),
 *   once per colleague's period: answered « non » (DutyController@decline, she comes as
 *   reinforcement), she works and her sales go to the colleague on duty (one till at a time).
 * Background JSON polling (alarm, requests, live figures) and submitted forms are never blocked
 * (page views only): a sale sent from a page opened earlier is saved, and attributed by the time
 * it is recorded.
 */
class EnsureDutyTaken
{
    /** Routes reachable without answering the relief question. */
    private const OPEN_ROUTES = [
        'admin.duty.show',
        'admin.duty.take',
        'admin.duty.decline',
        'admin.duty.end',
        'admin.logout',
        'admin.profile.edit',
        'admin.profile.update',
        'admin.profile.password.update',
    ];

    /** Session key: id of the colleague's period for which the relief was declined. */
    public const DECLINED_SESSION_KEY = 'duty_relief_declined';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! config('hotel.duty_required', true)
            || ! $user->takesDuty()
            || $request->routeIs(self::OPEN_ROUTES)
            || ! $request->isMethodSafe()
            || $request->expectsJson()) {
            return $next($request);
        }

        $current = DutyShift::current();

        if ($current === null) {
            $shift = DutyShift::start($user);
            // Shown on this page, after any message already waiting (e.g. « Chambre affectée… »).
            $waiting = $request->session()->get('status');
            $request->session()->now('status', trim((is_string($waiting) ? $waiting.' ' : '').DutyController::startedMessage($shift)));

            return $next($request);
        }

        if ($current->user_id === $user->id
            || (int) $request->session()->get(self::DECLINED_SESSION_KEY) === $current->id) {
            return $next($request);
        }

        return redirect()->route('admin.duty.show');
    }
}
