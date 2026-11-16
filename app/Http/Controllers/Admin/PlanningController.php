<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StayRequest;
use App\Models\User;
use App\Support\BusinessDay;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

/**
 * « Planning » (V2, client request 2026-11-15): rooms × time, stays and bookings as coloured
 * blocks. Read only — every action goes through the existing screens (file page, réservation
 * form pre-filled, board assignment modal), which keep all the availability checks.
 * Columns follow the business day (06:00 → 05:59). Guest names only on bookings not yet
 * started, never on stays (same rule as the room board).
 */
class PlanningController extends Controller
{
    public const VIEWS = ['jour' => 'Jour', 'semaine' => 'Semaine', 'mois' => 'Mois'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $text = fn (string $key): string => is_string($value = $request->query($key)) ? $value : '';
        $view = array_key_exists($text('vue'), self::VIEWS) ? $text('vue') : (User::onPhone() ? 'jour' : 'semaine');
        $today = BusinessDay::today();
        try {
            $date = $text('date') !== '' ? Carbon::createFromFormat('!Y-m-d', $text('date')) : $today->copy();
        } catch (Throwable) {
            $date = $today->copy();
        }
        // Keep the navigation within a sane range (about two years each way).
        $date = $date->max($today->copy()->subYears(2))->min($today->copy()->addYears(2));

        [$firstDay, $days] = match ($view) {
            'jour' => [$date->copy(), 1],
            'semaine' => [$date->copy()->startOfWeek(), 7],
            default => [$date->copy()->startOfMonth(), $date->copy()->daysInMonth],
        };
        $start = BusinessDay::bounds($firstDay)[0];
        $end = BusinessDay::bounds($firstDay->copy()->addDays($days - 1))[1];
        $span = max(1, $start->diffInSeconds($end));
        $now = now();

        $typeId = ctype_digit($text('type')) ? (int) $text('type') : null;
        $rooms = Room::query()->with('roomType')
            ->when($typeId, fn ($q) => $q->where('room_type_id', $typeId))
            ->get()
            ->sort(fn (Room $a, Room $b) => strnatcasecmp((string) $a->roomType?->name, (string) $b->roomType?->name)
                ?: strnatcasecmp((string) $a->number, (string) $b->number))
            ->values();

        $files = Reservation::query()
            ->with(['guest', 'invoice'])
            ->whereIn('room_id', $rooms->pluck('id'))
            ->whereIn('status', [ReservationStatus::EnAttente, ReservationStatus::Confirmee, ReservationStatus::Enregistree, ReservationStatus::Terminee])
            ->where('check_in', '<', $end)
            ->where(fn ($q) => $q->where('check_out', '>', $start)->orWhere('status', ReservationStatus::Enregistree))
            ->get();
        $requests = StayRequest::query()->holding()->whereIn('room_id', $rooms->pluck('id'))
            ->where('check_in', '<', $end)->where('check_out', '>', $start)->get();

        $buffer = (int) config('hotel.cleaning_buffer_minutes', 0);
        $pos = fn (CarbonInterface $t) => max(0.0, min(100.0, $start->diffInSeconds($t, false) / $span * 100));
        $canStays = $user->can('affectations.view');
        $canBookings = $user->can('reservations.view');

        $blocks = collect();
        foreach ($files as $file) {
            $stay = $file->isStay();
            $overdue = $file->status === ReservationStatus::Enregistree && $file->check_out->lessThan($now);
            $to = $overdue ? $now->copy() : $file->check_out;
            if ($to->lessThanOrEqualTo($start)) {
                continue;
            }
            $kind = match (true) {
                $file->status === ReservationStatus::Terminee => 'done',
                $stay => 'stay',
                $file->is_guaranteed => 'booking',
                default => 'booking-unpaid',
            };
            $remaining = $file->invoice ? (float) $file->invoice->remainingAmount() : 0.0;
            $label = $stay ? $file->reference() : $file->reference().' · '.$file->guest?->fullName();
            $blocks->push([
                'room_id' => $file->room_id,
                'from' => $file->check_in,
                'to' => $to,
                'kind' => $kind,
                'mode' => $file->pricing_mode,
                'label' => $label,
                'url' => ($stay ? $canStays : $canBookings) ? $file->fileUrl() : null,
                'overdue' => $overdue,
                'buffer' => $file->status !== ReservationStatus::Terminee && $buffer > 0,
                'tip' => implode(' · ', array_filter([
                    $file->kindLabel().' '.$file->reference(),
                    $stay ? null : $file->guest?->fullName(),
                    $file->modeLabel(),
                    'Entrée '.$file->check_in->format('d/m H\hi'),
                    'Sortie '.$file->check_out->format('d/m H\hi').($overdue ? ' (dépassée)' : ''),
                    number_format((float) $file->total_amount, 0, ',', ' ').' F',
                    $remaining > 0 ? 'Reste dû '.number_format($remaining, 0, ',', ' ').' F' : 'Soldé',
                    $kind === 'booking-unpaid' ? 'Non soldée : ne bloque pas encore la chambre' : null,
                ])),
            ]);
        }
        foreach ($requests as $req) {
            $blocks->push([
                'room_id' => $req->room_id, 'from' => $req->check_in, 'to' => $req->check_out, 'kind' => 'request', 'mode' => $req->pricing_mode,
                'label' => 'Demande client', 'url' => $canStays ? route('admin.stay-requests.show', $req) : null, 'overdue' => false, 'buffer' => false,
                'tip' => 'Demande client (QR code) en attente · '.$req->check_in->format('d/m H\hi').' → '.$req->check_out->format('d/m H\hi').' · expire à '.$req->expires_at->format('H\hi'),
            ]);
        }

        // Geometry (percentages of the window) and lanes when blocks overlap on one room.
        $rows = $rooms->map(function (Room $room) use ($blocks, $pos, $buffer) {
            $lanes = [];
            $items = $blocks->where('room_id', $room->id)->sortBy(fn ($b) => $b['from']->getTimestamp())->values()->map(function ($b) use (&$lanes, $pos, $buffer) {
                $lane = 0;
                while (isset($lanes[$lane]) && $lanes[$lane]->greaterThan($b['from'])) {
                    $lane++;
                }
                $lanes[$lane] = $b['to'];

                return $b + [
                    'lane' => $lane,
                    'left' => $pos($b['from']),
                    'width' => max(0.4, $pos($b['to']) - $pos($b['from'])),
                    'bufferWidth' => $b['buffer'] ? max(0.0, $pos($b['to']->copy()->addMinutes($buffer)) - $pos($b['to'])) : 0.0,
                    'clippedStart' => $pos($b['from']) <= 0.0,
                    'clippedEnd' => $pos($b['to']) >= 100.0,
                ];
            });

            return ['room' => $room, 'blocks' => $items, 'lanes' => max(1, count($lanes))];
        });
        if ($text('libres') === '1') {
            $rows = $rows->filter(fn ($row) => $row['blocks']->whereIn('kind', ['stay', 'booking', 'request'])->isEmpty() && $row['room']->status !== RoomStatus::HorsService)->values();
        }

        return view('admin.planning.index', [
            'view' => $view,
            'date' => $date,
            'firstDay' => $firstDay,
            'start' => $start,
            'end' => $end,
            'columns' => $this->columns($view, $start, $days),
            'rows' => $rows,
            'nowLeft' => $now->between($start, $end) ? $pos($now) : null,
            'prev' => $this->shift($view, $date, -1),
            'next' => $this->shift($view, $date, 1),
            'today' => $today,
            'types' => RoomType::query()->orderBy('name')->get(['id', 'name']),
            'typeId' => $typeId,
            'freeOnly' => $text('libres') === '1',
            'canBook' => $user->can('reservations.manage'),
            'canAssign' => $user->can('affectations.manage'),
            'availableNow' => Room::query()->whereIn('id', $rooms->pluck('id'))->availableNow()->pluck('id')->flip(),
        ]);
    }

    /**
     * @return Collection<int, array{label: string, sub: string, today: bool}>
     */
    private function columns(string $view, Carbon $start, int $days): Collection
    {
        $today = BusinessDay::today();
        if ($view === 'jour') {
            return collect(range(0, 23))->map(fn ($h) => ['label' => $start->copy()->addHours($h)->format('H\h'), 'sub' => '', 'today' => false]);
        }

        return collect(range(0, $days - 1))->map(function ($d) use ($start, $today, $view) {
            $day = $start->copy()->addDays($d);

            return [
                'label' => $view === 'mois' ? $day->format('d') : ucfirst($day->translatedFormat('D d/m')),
                'sub' => $view === 'mois' ? mb_substr($day->translatedFormat('D'), 0, 2) : '',
                'today' => $day->isSameDay($today),
            ];
        });
    }

    private function shift(string $view, Carbon $date, int $step): string
    {
        $target = match ($view) {
            'jour' => $date->copy()->addDays($step),
            'semaine' => $date->copy()->addWeeks($step),
            default => $date->copy()->startOfMonth()->addMonthsNoOverflow($step),
        };

        return $target->format('Y-m-d');
    }
}
