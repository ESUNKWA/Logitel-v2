@extends('admin.layouts.admin')

@section('title', $department->getLabel())

@section('content')
    @php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))
    @php($slug = $department->slug())

    <div class="admin-page-header">
        <div>
            <h1><i class="far {{ $department->icon() }} text-primary me-2"></i>{{ $department->getLabel() }}</h1>
            <div class="dash-subtitle">Ventes de la journée du {{ $day->format('d/m/Y') }}@if ($day->equalTo($today)) (aujourd'hui)@endif.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.sales.create', $slug) }}" class="btn btn-primary"><i class="far fa-plus"></i> Nouvelle vente</a>
            @can('catalog.manage')
                <a href="{{ route('admin.catalog.index', ['service' => $slug]) }}" class="btn btn-outline-secondary"><i class="far fa-list"></i> Catalogue</a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('admin.sales.index', $slug) }}" class="admin-card">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label small" for="jour">Journée</label>
                <input type="date" class="form-control form-control-sm" id="jour" name="jour" value="{{ $day->toDateString() }}" max="{{ $today->toDateString() }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small" for="statut">Ventes</label>
                <select class="form-select form-select-sm" id="statut" name="statut">
                    <option value="validee" @selected($status === 'validee')>Validées</option>
                    <option value="annulee" @selected($status === 'annulee')>Annulées ({{ $totals['cancelled'] }})</option>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="far fa-filter"></i> Afficher</button>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4">
            <div class="admin-card mb-0 h-100">
                <div class="small text-muted">Ventes validées</div>
                <div class="h4 mb-0">{{ $totals['count'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="admin-card mb-0 h-100">
                <div class="small text-muted">Montant vendu</div>
                <div class="h4 mb-0">{{ $fcfa($totals['amount']) }} <small>FCFA</small></div>
            </div>
        </div>
        @if ($department->countsEntries())
            <div class="col-6 col-md-4">
                <div class="admin-card mb-0 h-100">
                    <div class="small text-muted">Entrées (fréquentation)</div>
                    <div class="h4 mb-0">{{ $totals['entries'] }}</div>
                </div>
            </div>
        @endif
    </div>

    <div class="admin-card p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Heure</th>
                        <th>Vente</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Reste dû</th>
                        <th>Saisie par</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        @php($remaining = max(0, (float) $sale->invoice?->amount - (float) $sale->invoice?->payments_sum_amount))
                        <tr>
                            <td class="text-nowrap">{{ $sale->sold_at->format('H\hi') }}</td>
                            <td>
                                <span class="file-ref">{{ $sale->reference() }}</span>
                                @if ($sale->label)
                                    · {{ $sale->label }}
                                @endif
                                @if ($sale->reservation)
                                    <div class="small text-muted"><i class="far fa-bed"></i> Reportée sur la chambre N°{{ $sale->reservation->room?->number }}</div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap fw-semibold">{{ $fcfa($sale->total_amount) }} F</td>
                            <td class="text-end text-nowrap">
                                @if ($sale->isCancelled())
                                    <span class="badge bg-secondary">Annulée</span>
                                @elseif ($remaining > 0)
                                    <span class="text-danger">{{ $fcfa($remaining) }} F</span>
                                @else
                                    <span class="badge bg-success">Soldée</span>
                                @endif
                            </td>
                            <td class="small">{{ $sale->creator?->name ?? '—' }}</td>
                            <td class="text-end"><a href="{{ route('admin.sales.show', [$slug, $sale]) }}" class="btn btn-sm btn-outline-primary">Ouvrir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucune vente {{ $status === 'annulee' ? 'annulée' : '' }} pour cette journée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $sales->links() }}
@endsection
