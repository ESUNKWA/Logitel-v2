@extends('admin.layouts.admin')

@section('title', 'Recettes')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $keep = array_filter(['par' => $manager ? $filters['staff'] : '', 'mode' => $filters['method']?->value]);
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
            <a href="{{ route('admin.revenue.export', request()->query()) }}" class="btn btn-outline-primary"><i class="far fa-file-excel"></i> Exporter (Excel)</a>
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="far fa-print"></i> Imprimer</button>
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
            @if ($manager)
                <div class="col-12 col-md-auto">
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
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="far fa-filter"></i> Afficher</button>
            </div>
        </form>
    </div>

    {{-- Totals of the period (with the filters) --}}
    <div class="my-cash-summary mb-3">
        <div class="admin-card">
            <div class="text-muted small">Total encaissé</div>
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
                                <td><a href="{{ route('admin.revenue.index', ['du' => $filters['from']->toDateString(), 'au' => $filters['to']->toDateString(), 'par' => $key]) }}">{{ $row['name'] }}</a></td>
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

    {{-- One line per payment --}}
    <div class="admin-card p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 revenue-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Dossier</th>
                        <th>Client</th>
                        <th>Mode</th>
                        <th class="text-end" data-card-title>Montant</th>
                        <th class="text-end">Reçu / rendu</th>
                        <th>Encaissé par</th>
                        <th>Affecté par</th>
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
                            <td>
                                @if ($file)
                                    <x-admin::file-badge :file="$file" :reference="true" :link="true" />
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
                            <td>{{ $file?->guest?->fullName() ?? '—' }}</td>
                            <td class="text-nowrap"><i class="fas {{ $payment->method->icon() }}"></i> {{ $payment->method->getLabel() }}</td>
                            <td class="text-end fw-semibold text-nowrap">{{ $fcfa($payment->amount) }} F</td>
                            <td class="text-end small text-muted text-nowrap">
                                @if ($payment->amount_received !== null)
                                    {{ $fcfa($payment->amount_received) }} / {{ $fcfa($payment->change_given) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $payment->creator?->name ?? 'Non attribué' }}</td>
                            <td>
                                @if ($assignedBy)
                                    {{ $assignedBy->name }}
                                @elseif ($file && ! $file->isStay())
                                    <span class="text-muted small">Réservation (client pas encore arrivé)</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Aucun encaissement sur cette période.</td></tr>
                    @endforelse
                </tbody>
                @if ($payments->total() > 0)
                    <tfoot>
                        <tr><th colspan="4">Total de la période</th><th class="text-end">{{ $fcfa($summary['total']) }} F</th><th colspan="3"></th></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $payments->links() }}</div>
@endsection
