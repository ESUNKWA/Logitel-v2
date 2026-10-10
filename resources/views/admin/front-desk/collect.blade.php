@extends('admin.layouts.admin')

@section('title', 'À encaisser')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>À encaisser</h1>
            <div class="dash-subtitle">Séjours et réservations dont la facture n'est pas soldée (les annulations sont exclues).</div>
        </div>
        <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-primary">Toutes les factures</a>
    </div>

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
                <div class="fd-item__main">
                    <div class="fd-item__title">{{ $file?->guest?->fullName() }}</div>
                    <div><x-admin::file-badge :file="$file" :link="true" /> <span class="small text-muted">{{ $state }}</span></div>
                    <div>Chambre N°{{ $file?->room?->number }} · facture {{ $invoice->number }}</div>
                    <div class="small text-muted">{{ $file?->check_in?->format('d/m H\hi') }} → {{ $file?->check_out?->format('d/m H\hi') }}</div>
                </div>
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
            <div class="fd-empty"><i class="far fa-check-circle"></i> Tout est réglé : aucune facture en attente de paiement.</div>
        @endforelse
    </div>

    {{ $invoices->links() }}
@endsection
