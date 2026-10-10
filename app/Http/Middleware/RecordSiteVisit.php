<?php

namespace App\Http\Middleware;

use App\Models\Room;
use App\Models\SiteVisit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts the public website's page views (Site web → Statistiques). Only real pages: GET, 200,
 * HTML, not a prefetch, not a bot, not a logged-in staff member. Never breaks the page: any
 * failure is only logged.
 */
class RecordSiteVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($this->shouldRecord($request, $response)) {
                $this->record($request);
            }
        } catch (Throwable $e) {
            Log::warning('Visite du site non enregistrée : '.$e->getMessage());
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $request->ajax()
            && ! preg_match('/prefetch|prerender/i', (string) ($request->header('Purpose').$request->header('Sec-Purpose').$request->header('X-Moz')))
            && ! Auth::check()
            && ! SiteVisit::isBot($request->userAgent());
    }

    private function record(Request $request): void
    {
        $visitor = SiteVisit::visitorHash($request);
        $today = now()->toDateString();
        $isEntry = ! SiteVisit::query()->where('day', $today)->where('visitor', $visitor)->exists();
        [$source, $host] = SiteVisit::sourceOf($request->headers->get('referer'), $request->getHost());
        $route = $request->route()?->getName();

        SiteVisit::query()->insert([
            'day' => $today,
            'visited_at' => now(),
            'visitor' => $visitor,
            'is_entry' => $isEntry,
            'route' => $route ? mb_substr($route, 0, 60) : null,
            'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
            'label' => mb_substr($this->label($request, $route), 0, 160),
            // A visit coming from a page of the site itself (e.g. back the next morning) counts as direct.
            'source' => $source === 'internal' ? 'direct' : $source,
            'referrer_host' => $host,
            'device' => SiteVisit::deviceOf($request->userAgent()),
        ]);
    }

    private function label(Request $request, ?string $route): string
    {
        if ($route === 'rooms.show' && ($room = $request->route('room')) instanceof Room) {
            return 'Chambre : '.$room->displayName();
        }

        return SiteVisit::PAGES[$route] ?? ('/'.ltrim($request->path(), '/'));
    }
}
