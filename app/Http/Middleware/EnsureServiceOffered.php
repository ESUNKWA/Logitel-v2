<?php

namespace App\Http\Middleware;

use App\Enums\Department;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The pages of a service the hotel doesn't offer (Configuration → Hôtel → Services proposés) are
 * not found (404), even typed by hand: nothing can be sold or booked for it. The service is the
 * middleware parameter (Department value, e.g. `evenements`), else the `{department}` URL slug.
 */
class EnsureServiceOffered
{
    public function handle(Request $request, Closure $next, ?string $service = null): Response
    {
        $department = $service !== null
            ? Department::tryFrom($service)
            : Department::fromSlug((string) $request->route('department'));

        abort_unless($department?->isOffered(), 404);

        return $next($request);
    }
}
