<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * « Journal d'activité » (V2): read only — there is no route to change or delete a line.
 * Permission activity.view (the Gérant, and any role he grants it to).
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('admin.activity.index', [
            'filters' => $filters,
            'logs' => ActivityLog::query()->filtered($filters)->orderByDesc('created_at')->orderByDesc('id')->paginate(50)->withQueryString(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'categories' => ActivityLog::CATEGORIES,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        Audit::record('export.activity', 'export', 'Export Excel du journal d\'activité du '.$filters['from']->format('d/m/Y').' au '.$filters['to']->format('d/m/Y'));

        $logs = ActivityLog::query()->filtered($filters)->orderBy('created_at')->orderBy('id');
        // Typed text must never be read by Excel as a formula (CSV injection).
        $cell = fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($logs, $cell) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Heure', 'Utilisateur', 'Catégorie', 'Action', 'Référence', 'Détails', 'Adresse IP'], ';');
            $logs->chunk(500, function ($chunk) use ($out, $cell) {
                foreach ($chunk as $log) {
                    fputcsv($out, array_map($cell, [
                        $log->created_at->format('d/m/Y'), $log->created_at->format('H:i:s'), $log->user_name ?? '', $log->categoryLabel(),
                        $log->description, $log->reference ?? '', $log->changesText(), $log->ip ?? '',
                    ]), ';');
                }
            });
            fclose($out);
        }, 'journal-activite-'.$filters['from']->format('Y-m-d').'-au-'.$filters['to']->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Sanitised filters (plain strings only, unknown values ignored): calendar days ?du=&au=
     * (default: the last 7 days, swapped if reversed, capped at 366 days, future → today),
     * ?par=ID|systeme, ?categorie=, ?q= (description, reference or name, ≤ 100 chars).
     *
     * @return array{from: Carbon, to: Carbon, start: Carbon, end: Carbon, user: string, category: string|null, q: string}
     */
    private function filters(Request $request): array
    {
        $text = fn (string $key): string => is_string($value = $request->query($key)) ? trim($value) : '';
        $today = Carbon::today();
        $date = function (string $key) use ($text, $today): ?Carbon {
            try {
                return $text($key) !== '' ? Carbon::createFromFormat('!Y-m-d', $text($key))->min($today) : null;
            } catch (Throwable) {
                return null;
            }
        };

        $to = $date('au') ?? $today->copy();
        $from = $date('du') ?? $to->copy()->subDays(6);
        [$from, $to] = $from->greaterThan($to) ? [$to, $from] : [$from, $to];
        $from = $from->max($to->copy()->subDays(365));
        $user = $text('par');
        $category = $text('categorie');

        return [
            'from' => $from,
            'to' => $to,
            'start' => $from->copy()->startOfDay(),
            'end' => $to->copy()->addDay()->startOfDay(),
            'user' => $user === 'systeme' || ctype_digit($user) ? $user : '',
            'category' => array_key_exists($category, ActivityLog::CATEGORIES) ? $category : null,
            'q' => mb_substr($text('q'), 0, 100),
        ];
    }
}
