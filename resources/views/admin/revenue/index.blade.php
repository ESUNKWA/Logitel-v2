@extends('admin.layouts.admin')

@section('title', 'Recettes')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $keep = array_filter(['par' => $manager ? $filters['staff'] : '', 'mode' => $filters['method']?->value, 'dossier' => $filters['kind'], 'formule' => $filters['formula']?->value]);
        $kindLabels = [\App\Models\Payment::KIND_STAY => 'Affectations', \App\Models\Payment::KIND_BOOKING => 'Réservations'];
        $periodLabel = $filters['single']
            ? ucfirst($filters['from']->translatedFormat('l j F Y'))
            : 'Du '.$filters['from']->translatedFormat('j F Y').' au '.$filters['to']->translatedFormat('j F Y');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-cash-register me-2 text-primary"></i>Recettes</h1>
            <div class="dash-subtitle">
                {{ $manager ? 'Argent encaissé par tout le personnel' : 'Ce que vous avez encaissé' }} — {{ $periodLabel }}
                (journées commerciales, de {{ $filters['start']->format('H\hi') }} à {{ $filters['end']->copy()->subMinute()->format('H\hi') }}).
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap revenue-actions">
            <a href="{{ route('admin.revenue.export', request()->query()) }}" class="btn btn-outline-primary phone-hidden"><i class="far fa-file-excel"></i> Exporter (Excel)</a>
            <button type="button" class="btn btn-primary phone-hidden" onclick="window.print()"><i class="far fa-print"></i> Imprimer</button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="admin-card revenue-filters mb-3">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            @foreach (\App\Http\Controllers\Admin\RevenueController::PERIODS as $key => $label)
                <a href="{{ route('admin.revenue.index', ['periode' => $key] + $keep) }}" class="btn btn-sm {{ $filters['period'] === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
            @if ($filters['single'])
                <span class="ms-md-auto d-inline-flex gap-1">
                    <a href="{{ route('admin.revenue.index', ['jour' => $filters['from']->copy()->subDay()->toDateString()] + $keep) }}" class="btn btn-sm btn-outline-secondary" title="Jour précédent"><i class="far fa-chevron-left"></i> Jour précédent</a>
                    @unless ($filters['isToday'])
                        <a href="{{ route('admin.revenue.index', ['jour' => $filters['from']->copy()->addDay()->toDateString()] + $keep) }}" class="btn btn-sm btn-outline-secondary" title="Jour suivant">Jour suivant <i class="far fa-chevron-right"></i></a>
                    @endunless
                </span>
            @endif
        </div>
        <form method="GET" action="{{ route('admin.revenue.index') }}" class="row g-2 align-items-end">
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="du">Du</label>
                <input type="date" class="form-control form-control-sm" id="du" name="du" value="{{ $filters['from']->toDateString() }}" max="{{ \App\Support\BusinessDay::today()->toDateString() }}">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="au">Au</label>
                <input type="date" class="form-control form-control-sm" id="au" name="au" value="{{ $filters['to']->toDateString() }}" max="{{ \App\Support\BusinessDay::today()->toDateString() }}">
            </div>
            {{-- Manager on phones (client request, 2026-11-09): no « Réceptionniste » / « Dossier » filters —
                 unless in use (e.g. from the per-staff table or a kind band), so a filtered list always shows
                 why. Receptionists keep « Dossier » (they never get « Réceptionniste »). --}}
            @if ($manager)
                <div class="col-12 col-md-auto {{ $filters['staff'] === '' ? 'phone-hidden' : '' }}">
                    <label class="form-label small mb-1" for="par">Réceptionniste</label>
                    <select class="form-select form-select-sm" id="par" name="par">
                        <option value="">Tout le personnel</option>
                        @foreach ($staffOptions as $member)
                            <option value="{{ $member->id }}" @selected($filters['staff'] === (string) $member->id)>{{ $member->name }}</option>
                        @endforeach
                        <option value="aucun" @selected($filters['staff'] === 'aucun')>Non attribué (anciens paiements)</option>
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-auto">
                <label class="form-label small mb-1" for="mode">Mode de paiement</label>
                <select class="form-select form-select-sm" id="mode" name="mode">
                    <option value="">Tous</option>
                    @foreach (\App\Enums\PaymentMethod::cases() as $method)
                        <option value="{{ $method->value }}" @selected($filters['method'] === $method)>{{ $method->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-auto {{ $manager && $filters['kind'] === null ? 'phone-hidden' : '' }}">
                <label class="form-label small mb-1" for="dossier">Dossier</label>
                <select class="form-select form-select-sm" id="dossier" name="dossier">
                    <option value="">Affectations et réservations</option>
                    @foreach ($kindLabels as $kind => $label)
                        <option value="{{ $kind }}" @selected($filters['kind'] === $kind)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <label class="form-label small mb-1" for="formule">Formule</label>
                <select class="form-select form-select-sm" id="formule" name="formule">
                    <option value="">Toutes</option>
                    @foreach (\App\Enums\PricingMode::cases() as $formula)
                        <option value="{{ $formula->value }}" @selected($filters['formula'] === $formula)>{{ $formula->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="far fa-filter"></i> Afficher</button>
            </div>
        </form>
    </div>

    {{-- Affectations vs réservations (option B: a réservation's payment recorded before the guest
         arrived counts as a réservation's; its balance paid once the guest is in, as an affectation's).
         PC: the full card (unchanged). Phones only (client request, 2026-11-09): a compact band, the
         per-method detail moves to a popup opened by ⓘ. The card / band filters the page. --}}
    <div class="revenue-kinds mb-3">
        @foreach ($byKind as $kind => $part)
            <div class="admin-card revenue-kind revenue-kind--{{ $kind }} {{ $filters['kind'] === $kind ? 'is-active' : '' }}">
                <a href="{{ route('admin.revenue.index', array_filter(['du' => $filters['from']->toDateString(), 'au' => $filters['to']->toDateString(), 'par' => $manager ? $filters['staff'] : '', 'mode' => $filters['method']?->value, 'dossier' => $filters['kind'] === $kind ? null : $kind, 'formule' => $filters['formula']?->value])) }}"
                    class="revenue-kind__link"
                    title="{{ $filters['kind'] === $kind ? 'Afficher tout' : 'N\'afficher que les '.mb_strtolower($kindLabels[$kind]) }}">
                    <span class="revenue-kind__label">
                        <x-admin::kind-icon :kind="$kind === \App\Models\Payment::KIND_BOOKING ? 'reservation' : 'affectation'" />{{ $kindLabels[$kind] }}
                    </span>
                    <span class="revenue-kind__total">{{ $fcfa($part['total']) }} <small>FCFA</small></span>
                    <span class="revenue-kind__count"><span class="revenue-kind__count-long">{{ $part['count'] }} encaissement(s)</span><span class="revenue-kind__count-short">{{ $part['count'] }} enc.</span></span>
                    <span class="revenue-kind__methods">
                        @foreach ($part['byMethod'] as $row)
                            @if ($row['amount'] > 0)
                                <span><i class="fas {{ $row['method']->icon() }}"></i> {{ $row['method']->getLabel() }} {{ $fcfa($row['amount']) }}</span>
                            @endif
                        @endforeach
                    </span>
                </a>
                <button type="button" class="revenue-kind__info" data-bs-toggle="modal" data-bs-target="#revenueKind-{{ $kind }}"
                    title="Détail des {{ mb_strtolower($kindLabels[$kind]) }}" aria-label="Détail des {{ mb_strtolower($kindLabels[$kind]) }}">
                    <i class="far fa-info-circle"></i>
                </button>
            </div>
        @endforeach
    </div>

    @foreach ($byKind as $kind => $part)
        <div class="modal fade" id="revenueKind-{{ $kind }}" tabindex="-1" aria-labelledby="revenueKind-{{ $kind }}-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content revenue-kind--{{ $kind }}">
                    <div class="modal-header">
                        <h5 class="modal-title revenue-kind__label" id="revenueKind-{{ $kind }}-title">
                            <x-admin::kind-icon :kind="$kind === \App\Models\Payment::KIND_BOOKING ? 'reservation' : 'affectation'" />{{ $kindLabels[$kind] }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="small text-muted">{{ $periodLabel }}</div>
                        <div class="fs-3 fw-bold">{{ $fcfa($part['total']) }} <small class="fs-6 text-muted">FCFA</small></div>
                        <div class="small text-muted mb-3">{{ $part['count'] }} encaissement(s)</div>
                        <table class="table table-sm mb-2 table-keep">
                            <tbody>
                                @foreach ($part['byMethod'] as $row)
                                    <tr>
                                        <td><i class="fas {{ $row['method']->icon() }}"></i> {{ $row['method']->getLabel() }}</td>
                                        <td class="text-end fw-semibold">{{ $fcfa($row['amount']) }} F</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="small text-muted">
                            {{ $kind === \App\Models\Payment::KIND_BOOKING
                                ? "Paiements de réservations enregistrés avant l'arrivée du client."
                                : "Affectations, et soldes de réservations payés une fois le client arrivé." }}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('admin.revenue.index', array_filter(['du' => $filters['from']->toDateString(), 'au' => $filters['to']->toDateString(), 'par' => $manager ? $filters['staff'] : '', 'mode' => $filters['method']?->value, 'dossier' => $kind, 'formule' => $filters['formula']?->value])) }}" class="btn btn-sm btn-primary">
                            <i class="far fa-filter"></i> Voir ces encaissements
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Totals of the period (with the filters) --}}
    <div class="my-cash-summary mb-3">
        <div class="admin-card">
            <div class="text-muted small">Total encaissé{{ $filters['kind'] ? ' — '.mb_strtolower($kindLabels[$filters['kind']]) : '' }}{{ $filters['formula'] ? ' — '.mb_strtolower($filters['formula']->getLabel()) : '' }}</div>
            <div class="fs-3 fw-bold">{{ $fcfa($summary['total']) }} <small class="fs-6 text-muted">FCFA</small></div>
            <div class="small text-muted">{{ $summary['count'] }} encaissement(s)</div>
        </div>
        @foreach ($summary['byMethod'] as $row)
            <div class="admin-card">
                <div class="text-muted small"><i class="fas {{ $row['method']->icon() }}"></i> {{ $row['method']->getLabel() }}</div>
                <div class="fs-4 fw-bold">{{ $fcfa($row['amount']) }} <small class="fs-6 text-muted">FCFA</small></div>
                @if (! $manager && $row['method'] === \App\Enums\PaymentMethod::Especes && $row['amount'] > 0 && $filters['isToday'] && $filters['single'])
                    <div class="small fw-semibold text-success">À remettre en caisse</div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($manager && $staff)
        <div class="admin-card p-0 mb-3">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Par réceptionniste</th>
                            <th class="text-end">Encaiss.</th>
                            @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                <th class="text-end">{{ $method->getLabel() }}</th>
                            @endforeach
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staff as $row)
                            @php
                                $key = $row['user_id'] === null ? 'aucun' : (string) $row['user_id'];
                            @endphp
                            <tr class="{{ $filters['staff'] === $key ? 'table-active' : '' }}">
                                <td><a href="{{ route('admin.revenue.index', array_filter(['du' => $filters['from']->toDateString(), 'au' => $filters['to']->toDateString(), 'par' => $key, 'dossier' => $filters['kind'], 'formule' => $filters['formula']?->value])) }}">{{ $row['name'] }}</a></td>
                                <td class="text-end">{{ $row['count'] }}</td>
                                @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                    <td class="text-end">{{ $fcfa($row['byMethod'][$method->value]) }}</td>
                                @endforeach
                                <td class="text-end fw-bold">{{ $fcfa($row['total']) }} F</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- One line per payment. PC: every column (unchanged). Phones only (client request, 2026-11-09):
         the card keeps amount, date and formula; the other columns (cell-hide-sm) are dropped and
         « Détails » opens the rest in a popup — the line's hidden, server-rendered detail block, copied
         into #revenueLineModal by admin.js. The Formule and « Détails » columns are phone-only. --}}
    <div class="admin-card p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 revenue-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="cell-hide-sm">Dossier</th>
                        <th class="cell-show-sm">Formule</th>
                        <th class="cell-hide-sm">Client</th>
                        <th class="cell-hide-sm">Mode</th>
                        <th class="text-end" data-card-title>Montant</th>
                        <th class="text-end cell-hide-sm">Reçu / rendu</th>
                        <th class="cell-hide-sm">Encaissé par</th>
                        <th class="cell-hide-sm">Affecté par</th>
                        <th class="text-end cell-show-sm" aria-label="Détails"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        @php
                            $file = $payment->invoice?->reservation;
                            $assignedBy = $file?->assignedBy();
                        @endphp
                        <tr>
                            <td class="text-nowrap">{{ $payment->paid_at->format('d/m') }} <span class="text-muted">{{ $payment->paid_at->format('H\hi') }}</span></td>
                            <td class="cell-hide-sm">
                                @if ($file)
                                    <x-admin::file-badge :file="$file" :reference="true" :link="true" />
                                    <x-admin::mode-badge :mode="$file->pricing_mode" :until="$file->stay_until" class="ms-1" />
                                    @if ($payment->isBookingPayment())
                                        <div class="small revenue-line-kind">Encaissé sur réservation (avant l'arrivée)</div>
                                    @elseif ($file->origin === \App\Models\Reservation::ORIGIN_RESERVATION)
                                        <div class="small revenue-line-kind">Encaissé après l'arrivée (réservation {{ $file->bookingReference() }})</div>
                                    @endif
                                    <div class="small text-muted">
                                        Chambre N°{{ $file->room?->number }}
                                        @if ($payment->invoice)
                                            ·
                                            @can('invoices.view')
                                                <a href="{{ route('admin.invoices.show', $payment->invoice) }}">{{ $payment->invoice->number }}</a>
                                            @else
                                                {{ $payment->invoice->number }}
                                            @endcan
                                        @endif
                                    </div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="cell-show-sm">@if ($file)<x-admin::mode-badge :mode="$file->pricing_mode" :until="$file->stay_until" />@else — @endif</td>
                            <td class="cell-hide-sm">{{ $file?->guest?->fullName() ?? '—' }}</td>
                            <td class="text-nowrap cell-hide-sm"><i class="fas {{ $payment->method->icon() }}"></i> {{ $payment->method->getLabel() }}</td>
                            <td class="text-end fw-semibold text-nowrap">{{ $fcfa($payment->amount) }} F</td>
                            <td class="text-end small text-muted text-nowrap cell-hide-sm">
                                @if ($payment->amount_received !== null)
                                    {{ $fcfa($payment->amount_received) }} / {{ $fcfa($payment->change_given) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="cell-hide-sm">
                                {{ $payment->attributedTo?->name ?? 'Non attribué' }}
                                {{-- Typed by someone else than the receptionist of the day (prise de service). --}}
                                @if ($payment->created_by && $payment->created_by !== $payment->attributed_to)
                                    <div class="small text-muted">saisi par {{ $payment->creator?->name ?? '—' }}</div>
                                @endif
                            </td>
                            <td class="cell-hide-sm">
                                @if ($assignedBy)
                                    {{ $assignedBy->name }}
                                @elseif ($file && ! $file->isStay())
                                    <span class="text-muted small">Réservation (client pas encore arrivé)</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end cell-show-sm">
                                <button type="button" class="btn btn-sm btn-outline-primary revenue-line-btn" data-line-detail
                                    data-title="{{ $file ? $file->reference().' — ' : '' }}{{ $fcfa($payment->amount) }} F · {{ $payment->paid_at->format('d/m/Y H\hi') }}">
                                    <i class="far fa-info-circle"></i> Détails
                                </button>
                                <div class="revenue-detail" hidden>
                                    <dl class="revenue-detail__list">
                                        <dt>Dossier</dt>
                                        <dd>
                                            @if ($file)
                                                <x-admin::file-badge :file="$file" :reference="true" :link="true" />
                                            @else
                                                —
                                            @endif
                                        </dd>
                                        @if ($file)
                                            <dt>Chambre</dt><dd>N°{{ $file->room?->number }}</dd>
                                        @endif
                                        <dt>Paiement</dt><dd><i class="fas {{ $payment->method->icon() }}"></i> {{ $payment->method->getLabel() }}</dd>
                                        @if ($file && $payment->isBookingPayment())
                                            <dt>Type</dt><dd class="revenue-line-kind">Encaissé sur réservation (avant l'arrivée)</dd>
                                        @elseif ($file && $file->origin === \App\Models\Reservation::ORIGIN_RESERVATION)
                                            <dt>Type</dt><dd class="revenue-line-kind">Encaissé après l'arrivée (réservation {{ $file->bookingReference() }})</dd>
                                        @endif
                                        <dt>Client</dt><dd>{{ $file?->guest?->fullName() ?? '—' }}</dd>
                                        @if ($payment->invoice)
                                            <dt>Facture</dt>
                                            <dd>
                                                @can('invoices.view')
                                                    <a href="{{ route('admin.invoices.show', $payment->invoice) }}">{{ $payment->invoice->number }}</a>
                                                @else
                                                    {{ $payment->invoice->number }}
                                                @endcan
                                            </dd>
                                        @endif
                                        @if ($payment->amount_received !== null)
                                            <dt>Reçu / rendu</dt><dd>{{ $fcfa($payment->amount_received) }} F / {{ $fcfa($payment->change_given) }} F</dd>
                                        @endif
                                        <dt>Encaissé par</dt>
                                        <dd>
                                            {{ $payment->attributedTo?->name ?? 'Non attribué' }}
                                            {{-- Typed by someone else than the receptionist of the day (prise de service). --}}
                                            @if ($payment->created_by && $payment->created_by !== $payment->attributed_to)
                                                <span class="small text-muted">— saisi par {{ $payment->creator?->name ?? '—' }}</span>
                                            @endif
                                        </dd>
                                        <dt>Affecté par</dt>
                                        <dd>
                                            @if ($assignedBy)
                                                {{ $assignedBy->name }}
                                            @elseif ($file && ! $file->isStay())
                                                <span class="text-muted">Réservation (client pas encore arrivé)</span>
                                            @else
                                                —
                                            @endif
                                        </dd>
                                    </dl>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">Aucun encaissement sur cette période.</td></tr>
                    @endforelse
                </tbody>
                @if ($payments->total() > 0)
                    <tfoot>
                        <tr><th colspan="5">Total de la période</th><th class="text-end">{{ $fcfa($summary['total']) }} F</th><th colspan="4"></th></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="modal fade" id="revenueLineModal" tabindex="-1" aria-labelledby="revenueLineModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="revenueLineModalTitle" data-line-title>Encaissement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body" data-line-body></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-3">{{ $payments->links() }}</div>
@endsection
