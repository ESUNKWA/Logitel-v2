@extends('admin.layouts.admin')

@section('title', 'Arrivée du client')

{{-- Errors shown next to the encaissement, not at the top of the page. --}}
@section('inline-errors', '1')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $total = (float) ($invoice?->amount ?? $reservation->total_amount);
        $paid = (float) ($invoice?->amountPaid() ?? 0);
        $remaining = (float) ($invoice?->remainingAmount() ?? 0);
        // What is left after the deposit can be cashed now (all, part or nothing) — or stay due.
        $withPayment = $invoice && $remaining > 0 && auth()->user()->can('payments.create');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1><x-admin::kind-icon kind="reservation" />Arrivée du client</h1>
            <div class="dash-subtitle">
                <x-admin::file-badge :file="$reservation" /> · {{ $reservation->guest?->fullName() }}
                · chambre N°{{ $reservation->room?->number }}
            </div>
        </div>
        <a href="{{ route('admin.reservations.edit', $reservation) }}" class="btn btn-outline-secondary">Ouvrir la réservation</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="admin-card">
                <h5>Séjour</h5>
                <dl class="row mb-0">
                    <dt class="col-5">Client</dt>
                    <dd class="col-7">{{ $reservation->guest?->fullName() }}@if ($reservation->guest?->phone)<div class="small text-muted">{{ \App\Support\PhoneFormat::display($reservation->guest->phone) }}</div>@endif</dd>
                    <dt class="col-5">Chambre</dt>
                    <dd class="col-7">N°{{ $reservation->room?->number }} <span class="text-muted">· {{ $reservation->room?->roomType?->name }}</span></dd>
                    <dt class="col-5">Formule</dt>
                    <dd class="col-7">{{ $reservation->billingLabel() }}</dd>
                    <dt class="col-5">Arrivée prévue</dt>
                    <dd class="col-7">{{ $reservation->check_in->format('d/m/Y à H\hi') }}</dd>
                    <dt class="col-5">Départ prévu</dt>
                    <dd class="col-7">{{ $reservation->check_out->format('d/m/Y à H\hi') }}</dd>
                </dl>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="admin-card">
                <h5>Règlement</h5>
                <dl class="row mb-3" data-arrival-billing>
                    <dt class="col-6">Montant total</dt>
                    <dd class="col-6"><strong>{{ $fcfa($total) }} FCFA</strong></dd>
                    <dt class="col-6">Acompte déjà versé</dt>
                    <dd class="col-6">{{ $fcfa($paid) }} FCFA</dd>
                    <dt class="col-6">Reste à payer</dt>
                    <dd class="col-6">
                        @if ($remaining > 0)
                            <strong class="text-danger">{{ $fcfa($remaining) }} FCFA</strong>
                        @else
                            <span class="badge bg-success">Soldée</span>
                        @endif
                    </dd>
                </dl>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.reservations.check-in', $reservation) }}">
                    @csrf
                    @if ($withPayment)
                        <div class="assign-pay">
                            <div class="assign-pay__label">Encaissement <span class="text-muted fw-normal">— facultatif : tout, une partie, ou videz le montant pour le régler plus tard</span></div>
                            @include('admin.payments._lines', ['due' => $remaining, 'required' => false, 'prefill' => true, 'cashReceivedRequired' => true,
                                'sharedReceived' => true, 'idPrefix' => 'arrival', 'defaultAmount' => (string) (int) round($remaining)])
                        </div>
                    @elseif ($remaining > 0)
                        <p class="text-muted small mb-0">Le reste à payer pourra être encaissé depuis la facture.</p>
                    @endif

                    <div class="mt-4 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-success"><i class="fas fa-sign-in-alt"></i> Enregistrer l'arrivée</button>
                        <a href="{{ route('admin.reservations.index') }}" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
