@extends('admin.layouts.admin')

@section('title', 'Planning des salles')

@section('content')
    @php($weekdays = ['Dim.', 'Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.'])

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-calendar-week text-primary me-2"></i>Planning des salles</h1>
            <div class="dash-subtitle">Semaine du {{ $start->format('d/m/Y') }} au {{ $start->copy()->addDays(6)->format('d/m/Y') }}. Cliquez sur une case libre pour réserver.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.events.planning', ['semaine' => $start->copy()->subWeek()->toDateString()]) }}" class="btn btn-outline-secondary"><i class="far fa-chevron-left"></i> Semaine précédente</a>
            <a href="{{ route('admin.events.planning') }}" class="btn btn-outline-secondary">Cette semaine</a>
            <a href="{{ route('admin.events.planning', ['semaine' => $start->copy()->addWeek()->toDateString()]) }}" class="btn btn-outline-secondary">Semaine suivante <i class="far fa-chevron-right"></i></a>
            <a href="{{ route('admin.events.index') }}" class="btn btn-outline-primary">Liste des réservations</a>
        </div>
    </div>

    @if ($venues->isEmpty())
        <div class="alert alert-info">Aucune salle active.</div>
    @else
        <div class="admin-card p-0 venue-planning-grid">
            <div class="table-responsive">
                <table class="table table-bordered align-top mb-0 venue-planning table-keep">
                    <thead>
                        <tr>
                            <th>Salle</th>
                            @foreach ($days as $day)
                                <th class="{{ $day->isToday() ? 'is-today' : '' }}">{{ $weekdays[$day->dayOfWeek] }} {{ $day->format('d/m') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($venues as $venue)
                            <tr>
                                <th class="text-nowrap">{{ $venue->name }}<div class="small text-muted fw-normal">{{ $venue->capacity }} pers.</div></th>
                                @foreach ($days as $day)
                                    @php($dayBookings = $bookings->get($venue->id.'|'.$day->toDateString(), collect()))
                                    <td class="{{ $day->isToday() ? 'is-today' : '' }}">
                                        @foreach ($dayBookings as $booking)
                                            <a href="{{ route('admin.events.show', $booking) }}" class="venue-slot {{ $booking->isConfirmed() ? 'is-confirmed' : 'is-option' }}">
                                                <strong>{{ $booking->start_at->format('H\hi') }}–{{ $booking->end_at->format('H\hi') }}</strong>
                                                <span>{{ $booking->client_name }}</span>
                                                <small>{{ $booking->isConfirmed() ? 'Confirmée' : 'Option' }} · {{ $booking->participants }} pers.</small>
                                            </a>
                                        @endforeach
                                        @if (! $day->isPast() || $day->isToday())
                                            <a href="{{ route('admin.events.create', ['salle' => $venue->id, 'date' => $day->toDateString()]) }}" class="venue-slot__add" aria-label="Réserver {{ $venue->name }} le {{ $day->format('d/m') }}"><i class="far fa-plus"></i></a>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phones and tablets (the grid needs ~1040 px, admin.css): the same week, one card per day. --}}
        <div class="venue-days">
            @foreach ($days as $day)
                <section class="admin-card mb-0 venue-day {{ $day->isToday() ? 'is-today' : '' }}" aria-label="{{ $weekdays[$day->dayOfWeek] }} {{ $day->format('d/m') }}">
                    <h2 class="venue-day__title">
                        {{ $weekdays[$day->dayOfWeek] }} {{ $day->format('d/m') }}
                        @if ($day->isToday())
                            <span class="badge bg-success">Aujourd'hui</span>
                        @endif
                    </h2>
                    @foreach ($venues as $venue)
                        @php($dayBookings = $bookings->get($venue->id.'|'.$day->toDateString(), collect()))
                        <div class="venue-day__venue">
                            <div class="venue-day__name">{{ $venue->name }} <small>{{ $venue->capacity }} pers.</small></div>
                            @foreach ($dayBookings as $booking)
                                <a href="{{ route('admin.events.show', $booking) }}" class="venue-slot {{ $booking->isConfirmed() ? 'is-confirmed' : 'is-option' }}">
                                    <strong>{{ $booking->start_at->format('H\hi') }}–{{ $booking->end_at->format('H\hi') }}</strong>
                                    <span>{{ $booking->client_name }}</span>
                                    <small>{{ $booking->isConfirmed() ? 'Confirmée' : 'Option' }} · {{ $booking->participants }} pers.</small>
                                </a>
                            @endforeach
                            @if (! $day->isPast() || $day->isToday())
                                <a href="{{ route('admin.events.create', ['salle' => $venue->id, 'date' => $day->toDateString()]) }}" class="btn btn-sm btn-outline-primary venue-day__add"><i class="far fa-plus"></i> Réserver</a>
                            @elseif ($dayBookings->isEmpty())
                                <span class="small text-muted">Libre</span>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endforeach
        </div>
    @endif
@endsection
