@extends('admin.layouts.admin')

@section('title', 'À encaisser')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>À encaisser</h1>
            <div class="dash-subtitle">
                @if ($period === 'aujourdhui')
                    Ce qui doit être réglé aujourd'hui : clients installés, sorties non soldées, arrivées et événements du jour, ventes des services.
                @else
                    Séjours et réservations dont la facture n'est pas soldée (les annulations sont exclues).
                @endif
            </div>
        </div>
        <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-primary">Toutes les factures</a>
    </div>

    {{-- « Aujourd'hui » = due by the end of the business day (Invoice::scopeDueBy), the home card's link. --}}
    <ul class="nav nav-pills mb-3 reservation-tabs">
        @foreach (['aujourdhui' => 'Aujourd\'hui', 'tout' => 'Tout'] as $key => $label)
            <li class="nav-item">
                <a href="{{ route('admin.front-desk.collect', array_filter(['periode' => $key === 'tout' ? null : $key, 'q' => $q])) }}" class="nav-link {{ $period === $key ? 'active' : '' }}" @if ($period === $key) aria-current="page" @endif>
                    {{ $label }} <span class="badge bg-light text-dark">{{ $counts[$key] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    {{-- Free-text search (Invoice::scopeSearch), kept within the current tab. --}}
    <form method="GET" action="{{ route('admin.front-desk.collect') }}" class="admin-card collect-search" role="search">
        @if ($period === 'aujourdhui')
            <input type="hidden" name="periode" value="aujourdhui">
        @endif
        <div class="input-group">
            <span class="input-group-text"><i class="far fa-search"></i></span>
            <input type="search" name="q" value="{{ $q }}" class="form-control" maxlength="80" autocomplete="off" enterkeyhint="search"
                placeholder="Client, téléphone, chambre, n° de facture, table…" aria-label="Rechercher une facture à encaisser">
            <button type="submit" class="btn btn-primary">Rechercher</button>
        </div>
        @if ($q !== '')
            <div class="small text-muted mt-2">
                {{ $invoices->total() }} résultat{{ $invoices->total() > 1 ? 's' : '' }} pour « {{ $q }} »
                · <a href="{{ route('admin.front-desk.collect', $period === 'aujourdhui' ? ['periode' => 'aujourdhui'] : []) }}">Effacer la recherche</a>
            </div>
        @endif
    </form>

    <div class="fd-list">
        @forelse ($invoices as $invoice)
            @php
                $file = $invoice->reservation;
                $remaining = max(0, (float) $invoice->amount - (float) $invoice->payments_sum_amount);
                $state = match (true) {
                    $file?->status === \App\Enums\ReservationStatus::Enregistree => 'en cours',
                    $file?->status === \App\Enums\ReservationStatus::Terminee => 'terminée',
                    default => 'à venir',
                };
            @endphp
            <div class="fd-item">
                @if ($invoice->sale)
                    {{-- A sale of another service (restaurant, beach & pool), possibly charged to a room. --}}
                    @php
                        $sale = $invoice->sale;
                    @endphp
                    <div class="fd-item__main">
                        <div class="fd-item__title"><i class="far {{ $sale->department->icon() }} text-muted"></i> {{ $sale->department->getLabel() }}@if ($sale->label) · {{ $sale->label }}@endif</div>
                        <div>Vente du {{ $sale->sold_at->format('d/m H\hi') }} · facture {{ $invoice->number }}</div>
                        @if ($sale->reservation)
                            <div class="small text-muted">Reportée sur la chambre N°{{ $sale->reservation->room?->number }}</div>
                        @endif
                    </div>
                @else
                <div class="fd-item__main">
                    <div class="fd-item__title">{{ $file?->guest?->fullName() }}</div>
                    <div><x-admin::file-badge :file="$file" :link="true" /> <span class="small text-muted">{{ $state }}</span></div>
                    <div>Chambre N°{{ $file?->room?->number }} · facture {{ $invoice->number }}</div>
                    <div class="small text-muted">{{ $file?->check_in?->format('d/m H\hi') }} → {{ $file?->check_out?->format('d/m H\hi') }}</div>
                </div>
                @endif
                <div class="fd-item__when">
                    <span class="small text-muted">Total</span>
                    <strong>{{ $fcfa($invoice->amount) }}</strong>
                    @if ((float) $invoice->payments_sum_amount > 0)
                        <span class="small text-muted">payé {{ $fcfa($invoice->payments_sum_amount) }}</span>
                    @endif
                </div>
                <div class="fd-item__money">
                    <span class="small text-muted">Reste dû</span>
                    <strong class="text-danger">{{ $fcfa($remaining) }} FCFA</strong>
                </div>
                <div class="fd-item__actions">
                    @can('payments.create')
                        <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-primary"><i class="fas fa-coins"></i> Encaisser</a>
                    @else
                        <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-link">Ouvrir</a>
                    @endcan
                </div>
            </div>
        @empty
            @if ($q !== '')
                <div class="fd-empty"><i class="far fa-search"></i> Aucune facture à encaisser ne correspond à « {{ $q }} »@if ($period === 'aujourdhui') aujourd'hui (essayez l'onglet <a href="{{ route('admin.front-desk.collect', ['q' => $q]) }}">Tout</a>)@endif.</div>
            @else
                <div class="fd-empty"><i class="far fa-check-circle"></i> {{ $period === 'aujourdhui' ? 'Rien à encaisser aujourd\'hui.' : 'Tout est réglé : aucune facture en attente de paiement.' }}</div>
            @endif
        @endforelse
    </div>

    {{ $invoices->links() }}
@endsection
