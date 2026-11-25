<?php

namespace App\Support;

use App\Models\ActivityLog;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes the « Journal d'activité » (V2). The only place that inserts into activity_logs.
 * A failure is logged and never breaks the action being recorded (savepoint, so a PostgreSQL
 * transaction in progress stays usable); a line written inside a transaction that is rolled
 * back disappears with it — the journal only tells what really happened.
 * Never pass secrets (passwords, tokens) to it: callers whitelist what they record.
 */
class Audit
{
    private static int $muted = 0;

    /**
     * @param  list<array{label: string, old?: string|null, new?: string|null, masked?: bool}>  $changes
     */
    public static function record(string $action, string $category, string $description, ?string $subjectType = null, ?int $subjectId = null, ?string $reference = null, array $changes = [], ?int $userId = null, ?string $userName = null): void
    {
        if (self::$muted > 0) {
            return;
        }

        try {
            $user = Auth::user();
            $request = App::runningInConsole() && ! App::runningUnitTests() ? null : request();

            DB::transaction(function () use ($action, $category, $description, $subjectType, $subjectId, $reference, $changes, $userId, $userName, $user, $request) {
                $line = new ActivityLog;
                $line->forceFill([
                    'created_at' => now(),
                    'user_id' => $userId ?? $user?->getAuthIdentifier(),
                    'user_name' => mb_substr($userName ?? $user?->name ?? 'Système', 0, 120),
                    'action' => mb_substr($action, 0, 60),
                    'category' => array_key_exists($category, ActivityLog::CATEGORIES) ? $category : 'configuration',
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'reference' => $reference !== null ? mb_substr($reference, 0, 60) : null,
                    'description' => mb_substr($description, 0, 2000),
                    'changes' => $changes === [] ? null : array_values($changes),
                    'ip' => $request?->ip(),
                    'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                ])->save();
            });
        } catch (Throwable $e) {
            Log::error('Journal d\'activité : écriture impossible', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Runs `$callback` without journal lines (automatic consequences already told by the
     * action that caused them, e.g. a room becoming « Occupée » at check-in).
     */
    public static function muted(callable $callback): mixed
    {
        self::$muted++;
        try {
            return $callback();
        } finally {
            self::$muted--;
        }
    }

    /**
     * A value as shown in the journal (snapshot text, so later code changes never alter it).
     */
    public static function format(mixed $value, bool $money = false): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }
        if ($value instanceof BackedEnum) {
            return method_exists($value, 'getLabel') ? $value->getLabel() : (string) $value->value;
        }
        if ($value instanceof CarbonInterface) {
            return $value->format('d/m/Y H\hi');
        }
        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }
        if (is_iterable($value)) {
            return implode(', ', array_map(fn ($v) => self::format($v), is_array($value) ? $value : iterator_to_array($value)));
        }
        if ($money && is_numeric($value)) {
            return number_format((float) $value, 0, ',', ' ').' F';
        }

        return mb_strimwidth((string) $value, 0, 300, '…');
    }

    public static function money(mixed $amount): string
    {
        return number_format((float) $amount, 0, ',', ' ').' F';
    }
}
