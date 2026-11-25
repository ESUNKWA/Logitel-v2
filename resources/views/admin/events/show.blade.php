@extends('admin.layouts.admin')

@section('title', 'Réservation de salle '.$booking->reference())
@section('sweetalert-confirm', '1')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $paid = (float) ($invoice?->amountPaid() ?? 0);
        $remaining = (float) ($invoice?->remainingAmount() ?? 0);
    @endphp

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-briefcase text-primary me-2"></i>{{ $booking->venue?->name }} — {{ $booking->start_at->format('d/m/Y') }}</h1>
            <div class="dash-subtitle">
                {{ $booking->reference() }} ·
                <span class="badge bg-{{ $booking->isCancelled() ? 'secondary' : ($booking->isConfirmed() ? 'success' : 'warning text-dark') }}">{{ $booking->statusLabel() }}</span>
                · créée par {{ $booking->creator?->name ?? '—' }} le {{ $booking->created_at?->format('d/m/Y à H\hi') }}
                @if ($booking->confirmedBy)
                    · confirmée par {{ $booking->confirmedBy->name }} le {{ $booking->confirmed_at?->format('d/m/Y') }}
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.events.document', $booking) }}" class="btn btn-outline-primary phone-hidden" target="_blank" rel="noopener"><i class="fas fa-print"></i> {{ $booking->isConfirmed() ? 'Confirmation' : 'Devis' }}</a>
            @unless ($booking->isCancelled())
                @if ($remaining > 0)
                    @can('payments.create')
                        <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-primary"><i class="fas fa-coins"></i> Encaisser</a>
                    @endcan
                @endif
                @unless ($booking->isConfirmed())
                    <form method="POST" action="{{ route('admin.events.confirm', $booking) }}">
                        @csrf
                        <button type="submit" class="btn btn-success"><i class="far fa-check"></i> Confirmer</button>
                    </form>
                @endunless
                <a href="{{ route('admin.events.edit', $booking) }}" class="btn btn-outline-secondary"><i class="far fa-edit"></i> Modifier</a>
            @endunless
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="admin-card">
                <h5>Réservation</h5>
                <dl class="row mb-0">
                    <dt class="col-5">Salle</dt><dd class="col-7">{{ $booking->venue?->name }} <span class="text-muted">({{ $booking->venue?->capacity }} pers.)</span></dd>
                    <dt class="col-5">Créneau</dt><dd class="col-7">{{ $booking->start_at->format('d/m/Y') }}, {{ $booking->start_at->format('H\hi') }} – {{ $booking->end_at->format('H\hi') }}</dd>
                    <dt class="col-5">Formule</dt><dd class="col-7">{{ $booking->formulaLabel() }}</dd>
                    <dt class="col-5">Participants</dt><dd class="col-7">{{ $booking->participants }}</dd>
                    @if ($booking->title)<dt class="col-5">Objet</dt><dd class="col-7">{{ $booking->title }}</dd>@endif
                    <dt class="col-5">Client</dt><dd class="col-7">{{ $booking->client_name }}<div class="small text-muted">{{ \App\Support\PhoneFormat::display($booking->client_phone) }}</div></dd>
                    @if ($booking->organization)<dt class="col-5">Organisation</dt><dd class="col-7">{{ $booking->organization }}</dd>@endif
                    @if ($booking->notes)<dt class="col-5">Notes</dt><dd class="col-7">{!! nl2br(e($booking->notes)) !!}</dd>@endif
                </dl>
            </div>

            @if ($booking->isCancelled())
                <div class="admin-card">
                    <h5>Annulation</h5>
                    <p class="mb-0">Annulée par <strong>{{ $sale->cancelledBy?->name ?? '—' }}</strong> le {{ $sale->cancelled_at?->format('d/m/Y à H\hi') }}.<br>Motif : {{ $sale->cancel_reason }}</p>
                </div>
            @else
                @can('sales.cancel')
                    <div class="admin-card">
                        <h5>Annuler la réservation</h5>
                        @if ($paid > 0)
                            <p class="small text-muted mb-0">Un acompte a été encaissé : supprimez d'abord l'encaissement depuis la facture (remboursement), puis annulez.</p>
                        @else
                            <form method="POST" action="{{ route('admin.events.cancel', $booking) }}" data-confirm="La réservation sera annulée et la salle libérée. Continuer ?" data-confirm-button="Oui, annuler" data-confirm-danger>
                                @csrf
                                <label class="form-label" for="cancel_reason">Motif</label>
                                <input type="text" class="form-control @error('cancel_reason') is-invalid @enderror" id="cancel_reason" name="cancel_reason" maxlength="500" required>
                                @error('cancel_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <button type="submit" class="btn btn-outline-danger mt-2">Annuler la réservation</button>
                            </form>
                        @endif
                    </div>
                @endcan
            @endif
        </div>

        <div class="col-lg-6">
            <div class="admin-card">
                <h5>Détail et règlement</h5>
                <table class="table table-sm align-middle">
                    <tbody>
                        @foreach ($sale->lines as $line)
                            <tr>
                                <td>{{ $line->quantity }} × {{ $line->label }}</td>
                                <td class="text-end text-nowrap">{{ $fcfa($line->line_total) }} F</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        @if ((float) $sale->discount_amount > 0)
                            <tr><td>Remise @if ($sale->discount_reason)<span class="small text-muted">— {{ $sale->discount_reason }}</span>@endif</td><td class="text-end text-success text-nowrap">− {{ $fcfa($sale->discount_amount) }} F</td></tr>
                        @endif
                        <tr class="fw-bold"><td>Total</td><td class="text-end text-nowrap">{{ $fcfa($sale->total_amount) }} F</td></tr>
                    </tfoot>
                </table>
                <dl class="row mb-0">
                    <dt class="col-5">Facture</dt>
                    <dd class="col-7">@can('invoices.view')<a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice?->number }}</a>@else {{ $invoice?->number }} @endcan</dd>
                    <dt class="col-5">Encaissé</dt><dd class="col-7">{{ $fcfa($paid) }} F</dd>
                    <dt class="col-5">Reste dû</dt>
                    <dd class="col-7">
                        @if ($booking->isCancelled())
                            —
                        @elseif ($remaining > 0)
                            <strong class="text-danger">{{ $fcfa($remaining) }} F</strong>
                        @else
                            <span class="badge bg-success">Soldée</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>
@endsection
