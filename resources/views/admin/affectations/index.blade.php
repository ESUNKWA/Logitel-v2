@extends('admin.layouts.admin')

@section('title', 'Affectations')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $tabs = ['en-cours' => 'En cours', 'terminees' => 'Terminées', 'annulees' => 'Annulées', 'toutes' => 'Toutes'];
        $keep = fn (array $extra = []) => array_filter([...request()->except(['page', 'onglet']), ...$extra], fn ($v) => filled($v));
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>Affectations</h1>
            <div class="dash-subtitle">Séjours en cours et passés. Pour installer un client, cliquez sur une chambre libre du tableau des chambres.</div>
        </div>
        @can('rooms.view')
            <a href="{{ route('admin.rooms.board') }}#disponible" class="btn btn-primary"><i class="far fa-hotel"></i> Affecter une chambre</a>
        @endcan
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach ($tabs as $key => $label)
            <li class="nav-item">
                <a href="{{ route('admin.affectations.index', $keep(['onglet' => $key])) }}" class="nav-link {{ $tab === $key ? 'active' : '' }}">
                    {{ $label }} <span class="badge bg-light text-dark">{{ $counts[$key] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    {{-- Filters: one row above everything they scope (list, totals, tab counts). --}}
    <form method="GET" class="admin-card filter-bar" data-filter-form>
        <input type="hidden" name="onglet" value="{{ $tab }}">
        <div class="filter-bar__grid">
            <div class="filter-bar__search">
                <label class="form-label" for="f-q">Client</label>
                <input type="search" class="form-control" id="f-q" name="q" value="{{ $filters['q'] }}" placeholder="Nom, prénom ou téléphone…">
            </div>
            <div>
                <label class="form-label" for="f-periode">Entrée</label>
                <select class="form-select" id="f-periode" name="periode" data-period-select>
                    <option value="">Toutes les dates</option>
                    @foreach (\App\Http\Controllers\Admin\AffectationController::PERIODS as $value => $label)
                        <option value="{{ $value }}" @selected($filters['periode'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-bar__dates" data-period-dates @if ($filters['periode'] !== 'personnalisee') hidden @endif>
                <label class="form-label" for="f-du">Du / au</label>
                <div class="d-flex gap-1">
                    <input type="date" class="form-control" id="f-du" name="du" value="{{ $filters['du'] }}" aria-label="Du">
                    <input type="date" class="form-control" name="au" value="{{ $filters['au'] }}" aria-label="Au">
                </div>
            </div>
            <div>
                <label class="form-label" for="f-chambre">Chambre</label>
                <select class="form-select" id="f-chambre" name="chambre">
                    <option value="">Toutes</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected($filters['chambre'] === $room->id)>N°{{ $room->number }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="f-type">Type</label>
                <select class="form-select" id="f-type" name="type">
                    <option value="">Tous</option>
                    @foreach ($roomTypes as $type)
                        <option value="{{ $type->id }}" @selected($filters['type'] === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="f-mode">Mode</label>
                <select class="form-select" id="f-mode" name="mode">
                    <option value="">Tous</option>
                    @foreach (\App\Enums\PricingMode::cases() as $mode)
                        <option value="{{ $mode->value }}" @selected($filters['mode'] === $mode->value)>{{ $mode->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="f-paiement">Paiement</label>
                <select class="form-select" id="f-paiement" name="paiement">
                    <option value="">Tous</option>
                    @foreach (\App\Enums\InvoiceStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['paiement'] === $status->value)>{{ $status->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="f-origine">Origine</label>
                <select class="form-select" id="f-origine" name="origine">
                    <option value="">Toutes</option>
                    <option value="directe" @selected($filters['origine'] === 'directe')>Affectation directe</option>
                    <option value="reservation" @selected($filters['origine'] === 'reservation')>Sur réservation</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="f-tri">Trier par</label>
                <select class="form-select" id="f-tri" name="tri">
                    <option value="">Par défaut</option>
                    @foreach (\App\Http\Controllers\Admin\AffectationController::SORTS as $value => $label)
                        <option value="{{ $value }}" @selected($filters['tri'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="filter-bar__actions">
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" id="f-depasse" name="depasse" value="1" @checked($filters['depasse'])>
                <label class="form-check-label" for="f-depasse">Départs dépassés uniquement</label>
            </div>
            <div class="d-flex gap-2">
                @if ($hasFilters || $filters['tri'])
                    <a href="{{ route('admin.affectations.index', ['onglet' => $tab]) }}" class="btn btn-outline-secondary">Réinitialiser</a>
                @endif
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
            </div>
        </div>
    </form>

    <div class="filter-summary">
        <div><span>Séjours</span><strong>{{ $totals['count'] }}</strong></div>
        <div><span>Montant total</span><strong>{{ $fcfa($totals['billed']) }} <small>FCFA</small></strong></div>
        <div><span>Encaissé</span><strong class="text-success">{{ $fcfa($totals['paid']) }} <small>FCFA</small></strong></div>
        <div><span>Reste dû</span><strong class="{{ $totals['due'] > 0 ? 'text-danger' : '' }}">{{ $fcfa($totals['due']) }} <small>FCFA</small></strong></div>
    </div>

    <div class="admin-card">
        <table class="table align-middle affectation-table">
            <thead>
                <tr>
                    <th>Chambre</th>
                    <th>Client</th>
                    <th>Formule</th>
                    <th>Entrée</th>
                    <th>Sortie</th>
                    <th>Occupants</th>
                    <th>Facturation</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($affectations as $stay)
                    @php
                        $paidAmount = (float) ($stay->invoice?->payments_sum_amount ?? 0);
                        $remaining = max(0, (float) $stay->total_amount - $paidAmount);
                        $overdue = $stay->status === \App\Enums\ReservationStatus::Enregistree && $stay->check_out->isPast();
                    @endphp
                    <tr>
                        <td><strong>N°{{ $stay->room?->number }}</strong><div class="small text-muted">{{ $stay->room?->roomType?->name }}</div></td>
                        <td>{{ $stay->guest?->fullName() }}<div class="small text-muted">{{ $stay->guest?->phone }}</div></td>
                        <td>{{ $stay->pricing_mode->getLabel() }}<div class="small text-muted">{{ $stay->billingLabel() }}</div></td>
                        <td class="text-nowrap">{{ $stay->check_in->format('d/m/Y') }}<div class="small text-muted">{{ $stay->check_in->format('H\hi') }}</div></td>
                        <td class="text-nowrap">
                            {{ $stay->check_out->format('d/m/Y') }}
                            <div class="small {{ $overdue ? 'text-danger fw-bold' : 'text-muted' }}">{{ $stay->check_out->format('H\hi') }}</div>
                            @if ($overdue)
                                <span class="badge bg-danger">Dépassé</span>
                            @endif
                        </td>
                        <td class="text-nowrap">{{ $stay->adults }} ad.@if ($stay->children) · {{ $stay->children }} enf.@endif</td>
                        <td class="text-nowrap">
                            <strong>{{ $fcfa($stay->total_amount) }} FCFA</strong>
                            @if ($stay->hasDiscount())
                                <div class="small text-success" title="{{ $stay->discountLabel() }}">remise − {{ $fcfa($stay->discount_amount) }}</div>
                            @endif
                            <div class="small text-muted">encaissé {{ $fcfa($paidAmount) }}@if ($remaining > 0) · <span class="text-danger">reste {{ $fcfa($remaining) }}</span>@endif</div>
                            @if ($stay->invoice)
                                <span class="badge bg-{{ $stay->invoice->status->getColor() }}">{{ $stay->invoice->status->getLabel() }}</span>
                                <span class="small text-muted">{{ $stay->invoice->number }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $stay->status->affectationColor() }}">{{ $stay->status->affectationLabel() }}</span>
                            <div class="small text-muted">{{ $stay->isBooking() ? 'sur réservation' : 'affectation directe' }}</div>
                            <div class="small text-muted">par {{ $stay->creator?->name ?? '—' }}</div>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.affectations.show', $stay) }}" class="btn btn-sm btn-outline-primary">Ouvrir</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            {{ $hasFilters ? 'Aucune affectation ne correspond à ces filtres.' : 'Aucune affectation dans cette catégorie.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $affectations->links() }}
@endsection
