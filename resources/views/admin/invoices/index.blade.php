@extends('admin.layouts.admin')

@section('title', 'Factures')

@section('content')
    <div class="admin-page-header">
        <h1>Factures</h1>
    </div>

    <div class="admin-card">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-auto">
                <select name="type" class="form-select" onchange="this.form.submit()" aria-label="Type de dossier">
                    <option value="">Affectations et réservations</option>
                    <option value="affectation" @selected(request('type') === 'affectation')>Affectations</option>
                    <option value="reservation" @selected(request('type') === 'reservation')>Réservations</option>
                    @foreach (array_filter(\App\Enums\Department::salesServices(), fn ($d) => $d->isOffered() || request('type') === $d->value) as $saleService)
                        <option value="{{ $saleService->value }}" @selected(request('type') === $saleService->value)>{{ $saleService->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Numéro</th>
                    <th>Client</th>
                    <th>Dossier</th>
                    <th>Émise le</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->number }}</td>
                        @if ($invoice->sale)
                            <td>{{ $invoice->sale->label ?? '—' }}</td>
                            <td><span class="file-kind"><span class="file-badge"><i class="fas {{ $invoice->sale->department->icon() }}" aria-hidden="true"></i> {{ $invoice->sale->department->getLabel() }}</span>@if ($invoice->sale->isCancelled()) <span class="badge bg-secondary">vente annulée</span>@endif</span></td>
                        @else
                            <td>{{ $invoice->reservation?->guest?->fullName() }}</td>
                            <td><x-admin::file-badge :file="$invoice->reservation" :link="true" /></td>
                        @endif
                        <td>{{ $invoice->issued_at->format('d/m/Y') }}</td>
                        <td>{{ number_format((float) $invoice->amount, 0, ',', ' ') }} FCFA</td>
                        <td><span class="badge bg-{{ $invoice->status->getColor() }}">{{ $invoice->status->getLabel() }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary">Ouvrir</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Aucune facture pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $invoices->links() }}
@endsection
