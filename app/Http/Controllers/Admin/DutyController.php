<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureDutyTaken;
use App\Models\DutyShift;
use App\Models\User;
use App\Support\BusinessDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * « Prise de service » by periods (client request, 2026-11-25): one receptionist on duty at a
 * time, each with her own sales (DutyShift). A receptionist takes the service automatically when
 * nobody is on duty (EnsureDutyTaken), takes over from a colleague after confirming (« Prendre la
 * relève »), or comes as reinforcement; she ends her service herself (or when logging out). The
 * Gérant sees every period, corrects a period's receptionist, designates one for a day without
 * any, and ends a period left open.
 */
class DutyController extends Controller
{
    /** How many business days the Gérant's page lists. */
    private const HISTORY_DAYS = 31;

    /** The message shown when a receptionist goes on duty (automatically or by taking over). */
    public static function startedMessage(DutyShift $shift): string
    {
        [$total, $count] = $shift->gap;

        return 'Vous êtes de service depuis '.$shift->started_at->format('H\hi').' : vos encaissements vous sont attribués'
            .($count > 0 ? ', dont '.number_format($total, 0, ',', ' ').' FCFA encaissés avant votre arrivée, quand personne n\'était de service.' : '.');
    }

    public function show(Request $request): View|RedirectResponse
    {
        // The Gérant never takes the service (his sales go to the receptionist on duty too).
        if (! $request->user()->takesDuty()) {
            return redirect()->route($request->user()->can('duty.manage') ? 'admin.duty.index' : 'admin.dashboard');
        }

        return view('admin.duty.show', ['current' => DutyShift::current()]);
    }

    /** « Je prends mon service » / « Oui, je prends la relève »: on duty from now. */
    public function take(Request $request): RedirectResponse
    {
        abort_unless($request->user()->takesDuty(), 403);

        $previous = DutyShift::current();
        $shift = DutyShift::start($request->user());
        $request->session()->forget(EnsureDutyTaken::DECLINED_SESSION_KEY);

        $relieved = $previous && $previous->user_id !== $request->user()->id ? $previous->user?->name : null;

        return redirect()->route('admin.dashboard')->with('status', $relieved
            ? 'Vous avez pris la relève de '.$relieved.' à '.$shift->started_at->format('H\hi').' : vos encaissements vous sont attribués à partir de maintenant.'
            : self::startedMessage($shift));
    }

    /**
     * « Non, je viens en renfort »: she works without relieving the colleague on duty, whose till
     * receives her sales (one till at a time); asked again when another period starts.
     */
    public function decline(Request $request): RedirectResponse
    {
        abort_unless($request->user()->takesDuty(), 403);
        $current = DutyShift::current();
        if ($current && $current->user_id !== $request->user()->id) {
            $request->session()->put(EnsureDutyTaken::DECLINED_SESSION_KEY, $current->id);
        }

        return redirect()->route('admin.dashboard')->with('status', $current
            ? 'D\'accord : '.$current->user?->name.' reste de service, vos encaissements lui sont attribués.'
            : 'Personne n\'est de service.');
    }

    /** « Terminer mon service »: the next receptionist takes the service when she opens a page. */
    public function end(Request $request): RedirectResponse
    {
        abort_unless($request->user()->takesDuty(), 403);
        $ended = DutyShift::endFor($request->user());

        return redirect()->route('admin.dashboard')->with('status', $ended
            ? 'Votre service est terminé. La prochaine réceptionniste prendra le service à sa connexion.'
            : 'Vous n\'étiez pas de service.');
    }

    public function index(): View
    {
        $today = BusinessDay::today();
        $days = collect(range(0, self::HISTORY_DAYS - 1))->map(fn (int $i) => $today->copy()->subDays($i));
        [$from] = BusinessDay::bounds($days->last());
        [, $to] = BusinessDay::bounds($today);
        $shifts = DutyShift::query()->with(['user', 'assignedBy', 'endedBy'])->overlapping($from, $to)->orderBy('started_at')->get();

        return view('admin.duty.index', [
            'days' => $days->map(function (Carbon $day) use ($shifts) {
                [$start, $end] = BusinessDay::bounds($day);

                return ['date' => $day, 'shifts' => $shifts->filter(fn (DutyShift $s) => $s->started_at->lessThan($end) && ($s->ended_at === null || $s->ended_at->greaterThan($start)))->values()];
            }),
            'today' => $today,
            'current' => $shifts->firstWhere('ended_at', null),
            'receptionists' => $this->receptionists(),
        ]);
    }

    /**
     * The Gérant designates the receptionist of a day without any period (today or a past day):
     * every sale of that day is attributed to her.
     */
    public function assign(Request $request): RedirectResponse
    {
        $today = BusinessDay::today();
        $data = $request->validate([
            'business_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today->toDateString()],
            'user_id' => ['required', 'integer', Rule::in($this->receptionists()->pluck('id')->all())],
        ], [
            'business_date.before_or_equal' => 'On ne peut pas désigner la réceptionniste d\'une journée qui n\'a pas commencé.',
            'user_id.in' => 'Choisissez une réceptionniste (compte actif qui prend son service).',
        ]);

        $date = Carbon::createFromFormat('!Y-m-d', $data['business_date']);
        $shift = DutyShift::designateDay($date, User::findOrFail($data['user_id']), $request->user());

        return back()->with('status', $shift->user->name.' est la réceptionniste de la journée du '.$date->format('d/m/Y').' : toutes les ventes de cette journée lui sont attribuées.');
    }

    /** The Gérant changes the receptionist of a period: every sale of the period follows. */
    public function reassign(Request $request, DutyShift $shift): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::in($this->receptionists()->pluck('id')->all())],
        ], ['user_id.in' => 'Choisissez une réceptionniste (compte actif qui prend son service).']);

        $shift->reassign(User::findOrFail($data['user_id']), $request->user());

        return back()->with('status', $shift->user->name.' est la réceptionniste de la période du '.$shift->started_at->format('d/m/Y à H\hi')
            .($shift->ended_at ? ' au '.$shift->ended_at->format('d/m/Y à H\hi') : ' (en cours)').' : toutes les ventes de cette période lui sont attribuées.');
    }

    /** The Gérant ends a period left open (the receptionist left without ending her service). */
    public function close(Request $request, DutyShift $shift): RedirectResponse
    {
        abort_unless($shift->isOpen(), 404);
        $shift->close($request->user());

        return back()->with('status', 'Le service de '.$shift->user?->name.' est terminé. La prochaine réceptionniste prendra le service à sa connexion.');
    }

    /**
     * Active staff who take the service (role granting duty.take, never the Gérant).
     */
    private function receptionists()
    {
        return User::query()->with('role')->where('is_active', true)->orderBy('name')->get()
            ->filter(fn (User $u) => $u->takesDuty())->values();
    }
}
