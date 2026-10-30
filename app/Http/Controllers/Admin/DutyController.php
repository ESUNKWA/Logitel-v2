<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureDutyTaken;
use App\Models\DutyDay;
use App\Models\User;
use App\Support\BusinessDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * « Prise de service » (client request, 2026-10-25): one receptionist per business day, to whom
 * every sale of that day is attributed (DutyDay). The receptionist takes it herself (mandatory,
 * EnsureDutyTaken); the Gérant sees every day and designates / corrects the receptionist of a day.
 */
class DutyController extends Controller
{
    /** How many business days the Gérant's page lists. */
    private const HISTORY_DAYS = 31;

    public function show(Request $request): View|RedirectResponse
    {
        // The Gérant never takes the service (his sales go to the receptionist of the day too).
        if (! $request->user()->takesDuty()) {
            return redirect()->route($request->user()->can('duty.manage') ? 'admin.duty.index' : 'admin.dashboard');
        }

        $today = BusinessDay::today();

        return view('admin.duty.show', [
            'today' => $today,
            'bounds' => BusinessDay::bounds($today),
            'duty' => DutyDay::today(),
        ]);
    }

    public function take(Request $request): RedirectResponse
    {
        abort_unless($request->user()->takesDuty(), 403);

        $duty = DutyDay::take($request->user());
        $request->session()->forget(EnsureDutyTaken::DEFER_SESSION_KEY);
        [$total, $count] = $duty->salesSummary();

        return redirect()->route('admin.dashboard')
            ->with('status', 'Vous avez pris le service de la journée du '.$duty->business_date->format('d/m/Y').'. Toutes les ventes de la journée vous sont attribuées'
                .($count > 0 ? ', dont '.number_format($total, 0, ',', ' ').' FCFA déjà encaissés avant votre arrivée.' : '.'));
    }

    /**
     * « Je termine la journée précédente »: the outgoing receptionist keeps working until the
     * relief arrives, without taking the new day (her sales go to whoever takes it).
     */
    public function defer(Request $request): RedirectResponse
    {
        abort_unless($request->user()->takesDuty(), 403);

        $request->session()->put(EnsureDutyTaken::DEFER_SESSION_KEY, BusinessDay::today()->toDateString());

        return redirect()->route('admin.dashboard')
            ->with('status', 'D\'accord : vos ventes seront attribuées à la réceptionniste qui prendra le service de la journée.');
    }

    public function index(): View
    {
        $today = BusinessDay::today();
        $days = collect(range(0, self::HISTORY_DAYS - 1))->map(fn (int $i) => $today->copy()->subDays($i));
        $duties = DutyDay::query()->with(['user', 'assignedBy'])
            ->whereDate('business_date', '>=', $days->last()->toDateString())
            ->get()->keyBy(fn (DutyDay $d) => $d->business_date->toDateString());

        return view('admin.duty.index', [
            'days' => $days,
            'duties' => $duties,
            'today' => $today,
            'receptionists' => $this->receptionists(),
        ]);
    }

    /**
     * The Gérant designates or corrects the receptionist of a day (today or a past day): every
     * sale of that day is attributed to her.
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
        $duty = DutyDay::assign($date, User::findOrFail($data['user_id']), $request->user());

        return back()->with('status', $duty->user->name.' est la réceptionniste de la journée du '.$date->format('d/m/Y').' : toutes les ventes de cette journée lui sont attribuées.');
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
