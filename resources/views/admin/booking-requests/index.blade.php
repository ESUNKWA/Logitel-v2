@extends('admin.layouts.admin')

@section('title', 'Demandes en ligne')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1><x-admin::kind-icon kind="reservation" />Demandes de réservation en ligne</h1>
            <div class="dash-subtitle">
                Envoyées depuis le bouton « Réserver » du site (<a href="{{ route('booking.create') }}" target="_blank" rel="noopener">{{ route('booking.create') }}</a>).
                Elles ne bloquent aucune chambre : rappelez le client, puis créez la réservation ou refusez la demande.
            </div>
        </div>
        <a href="{{ route('admin.reservations.index') }}" class="btn btn-outline-primary"><i class="far fa-calendar-alt"></i> Réservations</a>
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach ($tabs as $key => $label)
            <li class="nav-item">
                <a href="{{ route('admin.booking-requests.index', ['onglet' => $key]) }}" class="nav-link {{ $tab === $key ? 'active' : '' }}">
                    {{ $label }} <span class="badge bg-light text-dark">{{ $counts[$key] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    @forelse ($requests as $item)
        <div class="admin-card booking-request-card mb-3">
            <div class="booking-request-card__head">
                <div>
                    <span class="badge bg-{{ $item->statusColor() }}">{{ $item->statusLabel() }}</span>
                    <span class="text-muted small ms-1">Demande n°{{ $item->id }} · reçue le {{ $item->created_at->format('d/m/Y à H\hi') }}</span>
                    @if ($item->isPending() && $item->check_in->isPast())
                        <span class="badge bg-danger ms-1">Date d'arrivée dépassée</span>
                    @endif
                </div>
                <div class="text-end">
                    <strong>{{ $fcfa($item->total_amount) }} FCFA</strong>
                    <div class="small text-muted">estimé · {{ $item->billingLabel() }}</div>
                </div>
            </div>
            <div class="booking-request-card__grid">
                <div>
                    <div class="booking-request-card__label">Client</div>
                    <strong>{{ $item->fullName() }}</strong>
                    <div><a href="tel:{{ preg_replace('/[^0-9+]/', '', $item->phone) }}"><i class="far fa-phone-alt"></i> {{ $item->phone }}</a></div>
                    @if ($item->email)
                        <div class="small"><a href="mailto:{{ $item->email }}">{{ $item->email }}</a></div>
                    @endif
                </div>
                <div>
                    <div class="booking-request-card__label">Séjour demandé</div>
                    <strong>{{ $item->roomType?->name ?? 'Type supprimé' }}</strong> · <x-admin::mode-badge :mode="$item->pricing_mode" />
                    <div>{{ $item->check_in->format('d/m/Y H\hi') }} → {{ $item->check_out->format('d/m/Y H\hi') }}</div>
                    <div class="small text-muted">{{ $item->adults }} adulte(s), {{ $item->children }} enfant(s)</div>
                </div>
                <div>
                    @if ($item->message)
                        <div class="booking-request-card__label">Message</div>
                        <div class="booking-request-card__message">{{ $item->message }}</div>
                    @endif
                    @if (! $item->isPending())
                        <div class="small text-muted mt-1">
                            {{ $item->status === \App\Models\BookingRequest::STATUS_REFUSED ? 'Refusée' : 'Traitée' }} par {{ $item->handler?->name ?? '—' }} le {{ $item->handled_at?->format('d/m/Y H\hi') }}
                            @if ($item->refusal_reason)<div>Motif : {{ $item->refusal_reason }}</div>@endif
                        </div>
                        @if ($item->reservation)
                            <div class="mt-1"><x-admin::file-badge :file="$item->reservation" :reference="true" :link="true" /></div>
                        @endif
                    @endif
                </div>
            </div>
            @if ($item->isPending())
                @can('reservations.manage')
                    <div class="booking-request-card__actions">
                        <a href="{{ route('admin.reservations.create', ['demande' => $item->id]) }}" class="btn btn-primary btn-sm"><i class="far fa-calendar-plus"></i> Créer la réservation</a>
                        <form method="POST" action="{{ route('admin.booking-requests.refuse', $item) }}" class="d-flex gap-2 flex-wrap" onsubmit="return confirm('Refuser cette demande ?');">
                            @csrf
                            <input type="text" name="refusal_reason" class="form-control form-control-sm" maxlength="500" placeholder="Motif (facultatif, visible par le client)" aria-label="Motif du refus">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Refuser</button>
                        </form>
                    </div>
                @endcan
            @endif
        </div>
    @empty
        <div class="admin-card text-center text-muted py-5">Aucune demande dans cet onglet.</div>
    @endforelse

    {{ $requests->links() }}
@endsection
