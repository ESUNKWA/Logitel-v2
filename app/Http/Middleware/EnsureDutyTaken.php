<?php

namespace App\Http\Middleware;

use App\Models\DutyDay;
use App\Support\BusinessDay;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * « Prise de service » is mandatory (client request, 2026-10-25): while nobody holds the service
 * of the current business day, a receptionist (User::takesDuty()) is sent to the prise-de-service
 * page before anything else — unless she said she is finishing the previous day and waits for
 * the relief (DutyController@defer, for this business day only). Once a colleague holds the day,
 * the others work normally: their sales go to her. Background JSON polling (alarm, requests,
 * live figures) is never blocked, so the outgoing receptionist's alarm keeps ringing; nor is a
 * submitted form (only page views are redirected): a sale sent at 06:05 from a page opened before
 * 06:00 is saved — and attributed to whoever takes the service — instead of being lost.
 */
class EnsureDutyTaken
{
    /** Routes reachable before taking the service. */
    private const OPEN_ROUTES = [
        'admin.duty.show',
        'admin.duty.take',
        'admin.duty.defer',
        'admin.logout',
        'admin.profile.edit',
        'admin.profile.update',
        'admin.profile.password.update',
    ];

    public const DEFER_SESSION_KEY = 'duty_deferred_for';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! config('hotel.duty_required', true)
            || ! $user->takesDuty()
            || $request->routeIs(self::OPEN_ROUTES)
            || ! $request->isMethodSafe()
            || $request->expectsJson()
            || $request->session()->get(self::DEFER_SESSION_KEY) === BusinessDay::today()->toDateString()
            || DutyDay::today() !== null) {
            return $next($request);
        }

        return redirect()->route('admin.duty.show');
    }
}
