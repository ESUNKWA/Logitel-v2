@extends('admin.layouts.admin')

@section('title', 'Sorties')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1><x-admin::kind-icon kind="affectation" />Sorties</h1>
            <div class="dash-subtitle">Séjours qui se terminent aujourd'hui (les dépassés en premier).</div>
        </div>
        @can('affectations.view')
            <a href="{{ route('admin.affectations.index') }}" class="btn btn-outline-primary">Tous les séjours en cours ({{ $inProgress }})</a>
        @endcan
    </div>

    <div class="fd-list">
        @forelse ($stays as $stay)
            @php
                $overdue = $stay->check_out->lessThanOrEqualTo(now());
                $remaining = max(0, (float) $stay->invoice?->amount - (float) $stay->invoice?->payments_sum_amount);
            @endphp
            <div class="fd-item {{ $overdue ? 'is-overdue' : '' }}">
                <div class="fd-item__main">
                    <div class="fd-item__title">Chambre N°{{ $stay->room?->number }} <small>{{ $stay->room?->roomType?->name }}</small> <span class="file-ref ms-1">{{ $stay->reference() }}</span></div>
                    <div>{{ $stay->guest?->fullName() }}@if ($stay->guest?->phone) · <a href="tel:{{ $stay->guest->phone }}">{{ $stay->guest->phone }}</a>@endif</div>
                    <div class="small text-muted">{{ $stay->billingLabel() }} · entrée {{ $stay->check_in->format('d/m H\hi') }}</div>
                </div>
                <div class="fd-item__when">
                    <span class="small text-muted">Sortie</span>
                    <strong>{{ $stay->check_out->format('H\hi') }}</strong>
                    @if ($overdue)
                        <span class="badge bg-danger">Dépassé depuis {{ $stay->check_out->diffForHumans(now(), true) }}</span>
                    @else
                        <span class="badge bg-warning text-dark">dans {{ now()->diffForHumans($stay->check_out, true) }}</span>
                    @endif
                </div>
                <div class="fd-item__money">
                    @if ($remaining > 0)
                        <span class="small text-muted">Reste dû</span>
                        <strong class="text-danger">{{ $fcfa($remaining) }} FCFA</strong>
                    @else
                        <span class="badge bg-success">Soldé</span>
                    @endif
                </div>
                <div class="fd-item__actions">
                    @if ($remaining > 0 && $stay->invoice)
                        @can('payments.create')
                            <a href="{{ route('admin.invoices.show', $stay->invoice) }}" class="btn btn-outline-primary"><i class="fas fa-coins"></i> Encaisser</a>
                        @endcan
                    @endif
                    @can('affectations.manage')
                        <form method="POST" action="{{ route('admin.affectations.check-out', $stay) }}"
                            onsubmit="return confirm('{{ $remaining > 0 ? 'Attention : il reste '.$fcfa($remaining).' FCFA à encaisser. ' : '' }}Confirmer la sortie du client (chambre N°{{ $stay->room?->number }}) ?');">
                            @csrf
                            <button type="submit" class="btn btn-warning"><i class="fas fa-sign-out-alt"></i> Check-out</button>
                        </form>
                    @endcan
                    <a href="{{ route('admin.affectations.show', $stay) }}" class="btn btn-link">Ouvrir</a>
                </div>
            </div>
        @empty
            <div class="fd-empty"><i class="far fa-check-circle"></i> Aucune sortie prévue d'ici la fin de la journée.</div>
        @endforelse
    </div>
@endsection
