@extends('admin.layouts.admin')

@section('title', 'Demandes clients')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>Demandes clients</h1>
            <div class="dash-subtitle">
                Demandes envoyées par les clients depuis leur téléphone (page <a href="{{ route('stay-request.index') }}" target="_blank" rel="noopener">{{ route('stay-request.index') }}</a>).
                Une demande en attente retient la chambre {{ config('hotel.request_expiry_minutes') }} min : validez-la (le client règle) ou refusez-la.
            </div>
        </div>
        <a href="{{ route('admin.stay-requests.qr') }}" class="btn btn-outline-primary"><i class="fas fa-qrcode"></i> QR code à afficher</a>
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach ($tabs as $key => $label)
            <li class="nav-item">
                <a href="{{ route('admin.stay-requests.index', ['onglet' => $key]) }}" class="nav-link {{ $tab === $key ? 'active' : '' }}">
                    {{ $label }} <span class="badge bg-light text-dark">{{ $counts[$key] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="admin-card p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Envoyée</th>
                        <th>Client</th>
                        <th>Chambre</th>
                        <th>Formule</th>
                        <th class="text-end">Total</th>
                        <th>État</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $item)
                        @php
                            [$statusLabel, $statusColor] = $item->displayStatus();
                        @endphp
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ $item->created_at->format('d/m H\hi') }}</td>
                            <td>{{ $item->fullName() }}<div class="small text-muted">{{ $item->phone }}</div></td>
                            <td>N°{{ $item->room?->number }}<div class="small text-muted">{{ $item->room?->roomType?->name }}</div></td>
                            <td>{{ $item->pricing_mode->getLabel() }}<div class="small text-muted">{{ $item->billingLabel() }}</div></td>
                            <td class="text-end">{{ $fcfa($item->total_amount) }} FCFA</td>
                            <td>
                                <span class="badge bg-{{ $statusColor }}">{{ $statusLabel }}</span>
                                @if ($item->isPending() && ! $item->isExpired())
                                    <div class="small text-muted">jusqu'à {{ $item->expires_at->format('H\hi') }}</div>
                                @elseif ($item->handler)
                                    <div class="small text-muted">par {{ $item->handler->name }}, {{ $item->handled_at?->format('d/m H\hi') }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.stay-requests.show', $item) }}" class="btn btn-sm {{ $item->isPending() ? 'btn-primary' : 'btn-outline-secondary' }}">
                                    {{ $item->isPending() ? 'Traiter' : 'Voir' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Aucune demande.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $requests->links() }}
@endsection
