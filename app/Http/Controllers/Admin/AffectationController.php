<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AffectationDeletionRequest;
use App\Http\Requests\Admin\AffectationExtensionRequest;
use App\Http\Requests\Admin\AffectationRequest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SpecialRate;
use App\Models\TrashedFile;
use App\Support\BusinessDay;
use App\Support\FileTrash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Affectations: stays in progress and past ones. They're created from the room board
 * (RoomAssignmentController) or when a réservation's guest arrives (ReservationController@checkIn).
 */
class AffectationController extends Controller
{
    /**
     * Tab => statuses shown.
     */
    private const TABS = [
        'en-cours' => [ReservationStatus::Enregistree],
        'terminees' => [ReservationStatus::Terminee],
        'annulees' => [ReservationStatus::Annulee],
        'toutes' => [ReservationStatus::Enregistree, ReservationStatus::Terminee, ReservationStatus::Annulee],
    ];

    public const PERIODS = [
        'aujourdhui' => "Aujourd'hui",
        '7j' => '7 derniers jours',
        '30j' => '30 derniers jours',
        'mois' => 'Ce mois',
        'personnalisee' => 'Dates personnalisées',
    ];

    public const SORTS = [
        'sortie' => 'Sortie la plus proche',
        'entree_desc' => 'Entrée la plus récente',
        'entree' => 'Entrée la plus ancienne',
        'montant_desc' => 'Montant décroissant',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $base = Reservation::query()->stays();
        $this->applyFilters($base, $filters);

        $query = (clone $base)->whereIn('status', self::TABS[$filters['onglet']]);

        // Totals of the filtered list (all pages), so they always agree with what's listed.
        $ids = (clone $query)->pluck('id');
        $billed = (float) (clone $query)->sum('total_amount');
        $paid = (float) Payment::query()->whereHas('invoice', fn (Builder $q) => $q->whereIn('reservation_id', $ids))->sum('amount');

        $affectations = $query
            ->with(['guest', 'room.roomType', 'creator', 'invoice' => fn ($q) => $q->withSum('payments', 'amount')])
            ->tap(fn (Builder $q) => $this->applySort($q, $filters))
            ->paginate(15)
            ->withQueryString();

        return view('admin.affectations.index', [
            'affectations' => $affectations,
            'filters' => $filters,
            'tab' => $filters['onglet'],
            'counts' => collect(self::TABS)->map(fn (array $statuses) => (clone $base)->whereIn('status', $statuses)->count()),
            'totals' => ['count' => $ids->count(), 'billed' => $billed, 'paid' => $paid, 'due' => max(0, $billed - $paid)],
            'rooms' => Room::query()->orderBy('number')->get(['id', 'number']),
            'roomTypes' => RoomType::query()->orderBy('name')->get(['id', 'name']),
            'trashCount' => $request->user()->can('affectations.delete') ? TrashedFile::query()->inTrash()->count() : null,
            'hasFilters' => collect($filters)->except(['onglet', 'tri'])->filter(fn ($v) => filled($v) && $v !== false)->isNotEmpty(),
        ]);
    }

    /**
     * Sanitised filter values: anything unknown or malformed is simply ignored.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $tab = $request->string('onglet')->toString();
        $date = function (string $key) use ($request): ?string {
            try {
                return filled($request->input($key)) ? Carbon::createFromFormat('Y-m-d', (string) $request->input($key))->toDateString() : null;
            } catch (Throwable) {
                return null;
            }
        };

        return [
            'onglet' => array_key_exists($tab, self::TABS) ? $tab : 'en-cours',
            'q' => trim(mb_substr($request->string('q')->toString(), 0, 100)),
            'periode' => array_key_exists($request->string('periode')->toString(), self::PERIODS) ? $request->string('periode')->toString() : null,
            'du' => $date('du'),
            'au' => $date('au'),
            'chambre' => $request->integer('chambre') ?: null,
            'type' => $request->integer('type') ?: null,
            'mode' => PricingMode::tryFrom($request->string('mode')->toString())?->value,
            'paiement' => InvoiceStatus::tryFrom($request->string('paiement')->toString())?->value,
            'origine' => in_array($request->string('origine')->toString(), ['directe', 'reservation'], true) ? $request->string('origine')->toString() : null,
            'depasse' => $request->boolean('depasse'),
            'tri' => array_key_exists($request->string('tri')->toString(), self::SORTS) ? $request->string('tri')->toString() : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $needle = '%'.mb_strtolower($filters['q']).'%';
            // The ORs are grouped so they stay inside the guest relation's constraint.
            $query->whereHas('guest', fn (Builder $g) => $g->where(fn (Builder $w) => $w
                ->whereRaw('lower(first_name) like ?', [$needle])
                ->orWhereRaw('lower(last_name) like ?', [$needle])
                ->orWhereRaw("lower(first_name || ' ' || last_name) like ?", [$needle])
                ->orWhereRaw('lower(company_name) like ?', [$needle])
                ->orWhere('phone', 'like', '%'.$filters['q'].'%')));
        }

        // Entry period, by business day (journée commerciale).
        $today = BusinessDay::today();
        [$from, $to] = match ($filters['periode']) {
            'aujourdhui' => BusinessDay::current(),
            '7j' => [BusinessDay::bounds($today->copy()->subDays(6))[0], BusinessDay::current()[1]],
            '30j' => [BusinessDay::bounds($today->copy()->subDays(29))[0], BusinessDay::current()[1]],
            'mois' => BusinessDay::monthBounds($today),
            'personnalisee' => [
                $filters['du'] ? BusinessDay::bounds(Carbon::parse($filters['du']))[0] : null,
                $filters['au'] ? BusinessDay::bounds(Carbon::parse($filters['au']))[1] : null,
            ],
            default => [null, null],
        };
        $query->when($from, fn ($q) => $q->where('check_in', '>=', $from))
            ->when($to, fn ($q) => $q->where('check_in', '<', $to));

        $query->when($filters['chambre'], fn ($q, $id) => $q->where('room_id', $id))
            ->when($filters['type'], fn ($q, $id) => $q->whereHas('room', fn ($r) => $r->where('room_type_id', $id)))
            ->when($filters['mode'], fn ($q, $mode) => $q->where('pricing_mode', $mode))
            ->when($filters['paiement'], fn ($q, $status) => $q->whereHas('invoice', fn ($i) => $i->where('status', $status)))
            ->when($filters['origine'], fn ($q, $origin) => $q->where('origin', $origin === 'directe' ? Reservation::ORIGIN_AFFECTATION : Reservation::ORIGIN_RESERVATION))
            ->when($filters['depasse'], fn ($q) => $q->where('status', ReservationStatus::Enregistree)->where('check_out', '<=', now()));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        match ($filters['tri'] ?? ($filters['onglet'] === 'en-cours' ? 'sortie' : 'entree_desc')) {
            'sortie' => $query->orderBy('check_out'),
            'entree' => $query->orderBy('check_in'),
            'montant_desc' => $query->orderByDesc('total_amount'),
            default => $query->orderByDesc('check_in'),
        };
        $query->orderBy('id');
    }

    public function show(Reservation $affectation): View
    {
        abort_unless($affectation->isStay(), 404);

        $affectation->load(['guest', 'room.roomType', 'invoice.payments', 'creator', 'checkedInBy', 'checkedOutBy', 'cancelledBy', 'stayRequest', 'discountedBy']);

        // Forfaits of the stay's entry day for its mode (shown next to the duration; an extension
        // or shortening gets the matching one again). Réservations never get one.
        $forfaits = $affectation->origin === Reservation::ORIGIN_AFFECTATION && $affectation->room?->roomType
            ? (SpecialRate::menuFor($affectation->room->roomType, BusinessDay::dateOf($affectation->check_in))[$affectation->pricing_mode->value] ?? [])
            : [];

        return view('admin.affectations.show', [
            'affectation' => $affectation,
            'forfaits' => $forfaits,
            'extensionQuotes' => $affectation->status === ReservationStatus::Enregistree ? $this->extensionQuotes($affectation, $forfaits) : [],
        ]);
    }

    /** How many « + N » choices the extension form previews exactly (beyond: computed on save). */
    private const EXTENSION_PREVIEW_MAX = 48;

    /**
     * Live preview of the extension form: for each « + N » units, the new departure, the new
     * total and the supplement — same rules as the model (forfait of the entry day for that
     * duration when cheaper, then the stay's discount). Display only: the server recomputes.
     *
     * @param  array<int, float>  $forfaits  duration => forfait price (entry day, this mode)
     * @return array<int, array{end: string, total: float, supplement: float}>
     */
    private function extensionQuotes(Reservation $stay, array $forfaits): array
    {
        $quotes = [];
        $current = (float) $stay->total_amount;

        for ($extra = 1; $extra <= self::EXTENSION_PREVIEW_MAX && $stay->duration + $extra <= 365; $extra++) {
            $duration = $stay->duration + $extra;
            [, $end] = $stay->pricing_mode->resolve($stay->check_in, $duration, null, $stay->stay_until);
            $normal = bcmul((string) $stay->unit_rate, (string) $duration, 2);
            $subtotal = isset($forfaits[$duration]) && bccomp((string) $forfaits[$duration], $normal, 2) < 0
                ? bcadd((string) $forfaits[$duration], '0', 2)
                : $normal;
            $total = (float) bcsub($subtotal, Reservation::discountFor($subtotal, $stay->discount_type, (string) ($stay->discount_value ?? '0')), 2);

            $quotes[$extra] = ['end' => $end->format('d/m/Y à H\hi'), 'total' => $total, 'supplement' => $total - $current];
        }

        return $quotes;
    }

    /**
     * Extends a stay in progress at the guest's request, at any time (client request,
     * 2026-10-25): + N units of its own formula; the supplement is cashed now or left due.
     */
    public function extend(AffectationExtensionRequest $request, Reservation $affectation): RedirectResponse
    {
        abort_unless($affectation->isStay() && $affectation->status === ReservationStatus::Enregistree, 404);
        // Cashing the supplement needs the payment permission too.
        abort_if($request->paymentLines() !== [] && $request->user()->cannot('payments.create'), 403);

        $before = (float) $affectation->total_amount;

        $payments = DB::transaction(function () use ($request, $affectation) {
            $duration = $request->newDuration();
            [, $end] = $affectation->pricing_mode->resolve($affectation->check_in, $duration, null, $affectation->stay_until);
            Room::lockAvailable($affectation->room_id, $affectation->check_in, $end, $affectation->id, 'extra');

            // The model re-derives check_out and total, re-bills the invoice and clears any alarm snooze.
            $affectation->update(['duration' => $duration]);

            return $request->paymentLines() !== []
                ? $affectation->invoice->refresh()->settle($request->paymentLines(), now(), 'Prolongation')
                : collect();
        });

        $cashed = (float) $payments->sum('amount');
        $change = (float) $payments->sum('change_given');
        $remaining = (float) $affectation->invoice->refresh()->remainingAmount();
        $fcfa = fn (float $v) => number_format($v, 0, ',', ' ');

        return redirect()
            ->route('admin.affectations.show', $affectation)
            ->with('receipt', $affectation->id)
            ->with('status', 'Séjour prolongé de '.$affectation->pricing_mode->durationLabel((int) $request->validated('extra'))
                .' : sortie le '.$affectation->check_out->format('d/m/Y à H\hi')
                .', supplément '.$fcfa((float) $affectation->total_amount - $before).' FCFA.'
                .($cashed > 0 ? ' Encaissé : '.$fcfa($cashed).' FCFA.' : '')
                .($change > 0 ? ' Monnaie à rendre : '.$fcfa($change).' FCFA.' : '')
                .($remaining > 0 ? ' Reste dû : '.$fcfa($remaining).' FCFA.' : ' Facture soldée.'));
    }

    public function update(AffectationRequest $request, Reservation $affectation): RedirectResponse
    {
        abort_unless($affectation->isStay() && $affectation->status === ReservationStatus::Enregistree, 404);

        // Lock the room and re-check before extending; the model re-derives check_out and total
        // and re-bills the invoice.
        DB::transaction(function () use ($request, $affectation) {
            [, $end] = $affectation->pricing_mode->resolve($affectation->check_in, (int) $request->validated('duration'), null, $affectation->stay_until);
            Room::lockAvailable($affectation->room_id, $affectation->check_in, $end, $affectation->id, 'duration');

            $affectation->update($request->stayAttributes());
        });

        return redirect()
            ->route('admin.affectations.show', $affectation)
            ->with('status', 'Affectation mise à jour : '.$affectation->billingLabel().' = '
                .number_format((float) $affectation->total_amount, 0, ',', ' ').' FCFA, départ le '.$affectation->check_out->format('d/m/Y à H\hi').'.');
    }

    public function checkOut(Reservation $affectation): RedirectResponse
    {
        abort_unless($affectation->isStay() && $affectation->status === ReservationStatus::Enregistree, 404);

        // The model event frees the room ("disponible").
        $affectation->update(['status' => ReservationStatus::Terminee]);

        return back()->with('status', "Séjour terminé (sortie validée) : la chambre N°{$affectation->room?->number} est de nouveau libre.");
    }

    /**
     * Gérant only: puts the ticked affectations in the « Corbeille » (FileTrash) — stay,
     * réservation it came from, invoice, payments, and a client with no other file — so every
     * figure drops them. Restorable for config('hotel.trash_retention_days') days.
     */
    public function destroyMany(AffectationDeletionRequest $request): RedirectResponse
    {
        $entries = FileTrash::trash($request->ids(), $request->reason());
        FileTrash::purgeExpired();

        $count = $entries->count();
        $paid = (float) $entries->sum('paid_amount');

        return back()->with('status', ($count > 1 ? "{$count} affectations mises" : 'Affectation mise').' à la corbeille ('
            .$entries->pluck('reference')->implode(', ').').'
            .($paid > 0 ? ' '.number_format($paid, 0, ',', ' ').' FCFA retirés des recettes.' : '')
            .($count > 1 ? ' Restaurables' : ' Restaurable').' pendant '.TrashedFile::retentionDays().' jours depuis la corbeille.');
    }

    public function cancel(Reservation $affectation): RedirectResponse
    {
        abort_unless($affectation->isStay() && $affectation->status === ReservationStatus::Enregistree, 404);

        $affectation->update(['status' => ReservationStatus::Annulee]);

        return back()->with('status', 'Affectation annulée.');
    }
}
