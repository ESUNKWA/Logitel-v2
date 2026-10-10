@extends('admin.layouts.admin')

@section('title', 'Arrivées du jour')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1><x-admin::kind-icon kind="reservation" />Arrivées du jour</h1>
            <div class="dash-subtitle">Réservations attendues aujourd'hui, pas encore arrivées.</div>
        </div>
        @can('reservations.manage')
            <a href="{{ route('admin.reservations.create') }}" class="btn btn-primary"><i class="far fa-plus"></i> Nouvelle réservation</a>
        @endcan
    </div>

    <div class="fd-list">
        @forelse ($bookings as $booking)
            @php
                $late = $booking->check_in->lessThanOrEqualTo(now());
                $paid = (float) $booking->invoice?->payments_sum_amount;
                $remaining = max(0, (float) $booking->invoice?->amount - $paid);
            @endphp
            <div class="fd-item">
                <div class="fd-item__main">
                    <div class="fd-item__title">{{ $booking->guest?->fullName() }} <span class="file-ref ms-1">{{ $booking->reference() }}</span></div>
                    <div>Chambre N°{{ $booking->room?->number }} <span class="text-muted">· {{ $booking->room?->roomType?->name }}</span>@if ($booking->guest?->phone) · <a href="tel:{{ $booking->guest->phone }}">{{ $booking->guest->phone }}</a>@endif</div>
                    <div class="small text-muted">{{ $booking->billingLabel() }} · départ {{ $booking->check_out->format('d/m H\hi') }}</div>
                </div>
                <div class="fd-item__when">
                    <span class="small text-muted">Arrivée</span>
                    <strong>{{ $booking->check_in->format('H\hi') }}</strong>
                    @if ($late)
                        <span class="badge bg-secondary">attendu depuis {{ $booking->check_in->diffForHumans(now(), true) }}</span>
                    @endif
                </div>
                <div class="fd-item__money">
                    @if ($remaining <= 0)
                        <span class="badge bg-success">Soldée</span>
                    @else
                        <span class="small text-muted">{{ $paid > 0 ? 'Acompte '.$fcfa($paid).' · reste' : 'À payer' }}</span>
                        <strong class="text-danger">{{ $fcfa($remaining) }} FCFA</strong>
                    @endif
                </div>
                <div class="fd-item__actions">
                    @if ($remaining > 0 && $booking->invoice)
                        @can('payments.create')
                            <a href="{{ route('admin.invoices.show', $booking->invoice) }}" class="btn btn-outline-primary"><i class="fas fa-coins"></i> Encaisser</a>
                        @endcan
                    @endif
                    @can('reservations.manage')
                        <form method="POST" action="{{ route('admin.reservations.check-in', $booking) }}" onsubmit="return confirm('Le client est arrivé ? La réservation devient une affectation (chambre N°{{ $booking->room?->number }}).');">
                            @csrf
                            <button type="submit" class="btn btn-success"><i class="fas fa-sign-in-alt"></i> Arrivée du client</button>
                        </form>
                    @endcan
                    <a href="{{ route('admin.reservations.edit', $booking) }}" class="btn btn-link">Ouvrir</a>
                </div>
            </div>
        @empty
            <div class="fd-empty"><i class="far fa-calendar-check"></i> Aucune arrivée prévue aujourd'hui.</div>
        @endforelse
    </div>
@endsection
