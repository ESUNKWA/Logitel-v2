@extends('admin.layouts.admin')

@section('title', 'Recherche')

@section('content')
    <form method="GET" action="{{ route('admin.front-desk.search') }}" class="fd-search" role="search">
        <div class="input-group input-group-lg">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="search" class="form-control" name="q" value="{{ $q }}" placeholder="Client, téléphone, n° de chambre ou de facture…" autocomplete="off" autofocus>
            <button class="btn btn-primary" type="submit">Chercher</button>
        </div>
    </form>

    @if ($q !== '')
        @if ($rooms->isEmpty() && $guests->isEmpty() && $invoices->isEmpty())
            <div class="fd-empty"><i class="far fa-search"></i> Aucun résultat pour « {{ $q }} ».</div>
        @endif

        @if ($rooms->isNotEmpty())
            <h2 class="fd-section">Chambres</h2>
            <div class="fd-list">
                @foreach ($rooms as $room)
                    @php($state = \App\Models\Room::DISPLAY_STATES[$room->displayState()])
                    <div class="fd-item">
                        <div class="fd-item__main">
                            <div class="fd-item__title">Chambre N°{{ $room->number }} <small>{{ $room->roomType?->name }}</small></div>
                            <div><span class="room-sections-nav__dot room-sections-nav__dot--{{ $state[1] }}"></span> {{ ['occupee' => 'Occupée', 'disponible' => 'Libre', 'reservee' => 'Réservée', 'demande' => 'Demande client', 'nettoyage' => 'En nettoyage', 'hors_service' => 'Hors service'][$room->displayState()] }}</div>
                            @if ($room->currentStay)
                                <div class="small text-muted">{{ $room->currentStay->guest?->fullName() }} · sortie {{ $room->currentStay->check_out->format('d/m H\hi') }}</div>
                            @endif
                        </div>
                        <div class="fd-item__actions">
                            @if ($room->currentStay)
                                @can('affectations.view')
                                    <a href="{{ route('admin.affectations.show', $room->currentStay) }}" class="btn btn-outline-primary">Ouvrir le séjour</a>
                                @endcan
                            @elseif ($room->isRequested())
                                @can('affectations.view')
                                    <a href="{{ route('admin.stay-requests.show', $room->pendingRequest) }}" class="btn btn-outline-primary">Traiter la demande</a>
                                @endcan
                            @else
                                <a href="{{ route('admin.rooms.board') }}#{{ $room->displayState() }}" class="btn btn-outline-primary">Voir au tableau</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($guests->isNotEmpty())
            <h2 class="fd-section">Clients</h2>
            <div class="fd-list">
                @foreach ($guests as $guest)
                    @php($last = $guest->reservations->first())
                    <div class="fd-item">
                        <div class="fd-item__main">
                            <div class="fd-item__title">{{ $guest->fullName() }}</div>
                            <div>@if ($guest->phone)<a href="tel:{{ $guest->phone }}">{{ $guest->phone }}</a> · @endif{{ $guest->reservations->count() }} séjour(s) / réservation(s)</div>
                            @if ($last)
                                <div class="small text-muted">Dernier : chambre N°{{ $last->room?->number }}, {{ $last->check_in->format('d/m/Y') }} — {{ $last->isStay() ? $last->status->affectationLabel() : $last->status->reservationLabel() }}</div>
                            @endif
                        </div>
                        <div class="fd-item__actions">
                            @if ($last)
                                @if ($last->isStay())
                                    @can('affectations.view')
                                        <a href="{{ route('admin.affectations.show', $last) }}" class="btn btn-outline-primary">Dernier séjour</a>
                                    @endcan
                                @else
                                    @can('reservations.view')
                                        <a href="{{ route('admin.reservations.edit', $last) }}" class="btn btn-outline-primary">Réservation</a>
                                    @endcan
                                @endif
                            @endif
                            @can('guests.manage')
                                <a href="{{ route('admin.guests.edit', $guest) }}" class="btn btn-link">Fiche client</a>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($invoices->isNotEmpty())
            <h2 class="fd-section">Factures</h2>
            <div class="fd-list">
                @foreach ($invoices as $invoice)
                    <div class="fd-item">
                        <div class="fd-item__main">
                            <div class="fd-item__title">{{ $invoice->number }} <span class="badge bg-{{ $invoice->status->getColor() }}">{{ $invoice->status->getLabel() }}</span></div>
                            <div>{{ $invoice->reservation?->guest?->fullName() }} · {{ number_format((float) $invoice->amount, 0, ',', ' ') }} FCFA</div>
                        </div>
                        <div class="fd-item__actions">
                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-outline-primary">Ouvrir</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
@endsection
