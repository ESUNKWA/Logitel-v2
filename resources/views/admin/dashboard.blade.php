@extends('admin.layouts.admin')

@section('title', 'Tableau de bord')

@php
    $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    // Same order and wording as the room board sections; [label, color, list filter].
    $statusMeta = collect(\App\Models\Room::DISPLAY_STATES)->map(fn ($meta, $state) => [$meta[0], $meta[1], $state])->all();
    [$bdStart] = \App\Support\BusinessDay::current();
@endphp

@section('content')
    <div class="admin-page-header">
        <div>
            <h1>Tableau de bord</h1>
            <div class="dash-subtitle">
                Journée commerciale du {{ $today->translatedFormat('l j F Y') }}
                · depuis {{ $bdStart->format('H\hi') }}
            </div>
        </div>
        @if ($overdueCheckouts > 0)
            <a href="{{ route('admin.rooms.index', ['status' => 'occupee']) }}" class="dash-alert-pill">
                <i class="fas fa-bell"></i> {{ $overdueCheckouts }} départ(s) dépassé(s)
            </a>
        @endif
    </div>

    {{-- ============================================================ Money --}}
    @can('dashboard.revenue')
        <div class="dash-grid dash-grid--money">
            <div class="dash-hero">
                <div class="dash-hero__label">Recettes encaissées ce mois</div>
                <div class="dash-hero__value">{{ $fcfa($revenueMonth) }}<small>FCFA</small></div>
                <div class="dash-hero__meta">
                    @if ($revenueMonthDelta !== null)
                        <span class="dash-delta {{ $revenueMonthDelta >= 0 ? 'is-up' : 'is-down' }}">
                            <i class="fas {{ $revenueMonthDelta >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                            {{ $revenueMonthDelta >= 0 ? '+' : '' }}{{ $revenueMonthDelta }} %
                        </span>
                        <span>vs le mois dernier à la même date</span>
                    @else
                        <span>Paiements reçus depuis le début du mois</span>
                    @endif
                </div>
                @php
                    $spark = collect($dailyRevenue)->pluck('amount');
                    $sparkMax = max(1, $spark->max());
                    $sparkPoints = $spark->values()->map(fn ($v, $i) => round($i * (300 / max(1, $spark->count() - 1)), 1).','.round(54 - ($v / $sparkMax) * 48, 1))->implode(' ');
                @endphp
                <svg class="dash-hero__spark" viewBox="0 0 300 60" preserveAspectRatio="none" aria-hidden="true">
                    <polyline points="0,60 {{ $sparkPoints }} 300,60" class="dash-hero__spark-area"></polyline>
                    <polyline points="{{ $sparkPoints }}" class="dash-hero__spark-line"></polyline>
                </svg>
            </div>

            <div class="dash-tile">
                <span class="dash-tile__icon dash-tile__icon--gold"><i class="fas fa-coins"></i></span>
                <div class="dash-tile__label">Recettes du jour</div>
                <div class="dash-tile__value">{{ $fcfa($revenueToday) }} <small>FCFA</small></div>
                <div class="dash-tile__hint">Encaissées depuis {{ $bdStart->format('H\hi') }}</div>
            </div>
            <div class="dash-tile">
                <span class="dash-tile__icon dash-tile__icon--primary"><i class="fas fa-file-invoice-dollar"></i></span>
                <div class="dash-tile__label">Chiffre d'affaires du mois</div>
                <div class="dash-tile__value">{{ $fcfa($monthlyRevenue) }} <small>FCFA</small></div>
                <div class="dash-tile__hint">
                    Facturé (séjours commencés ce mois)
                    @if ($monthlyDiscounts > 0)
                        , après <span class="text-success fw-semibold">{{ $fcfa($monthlyDiscounts) }} FCFA</span> de remises
                    @endif
                </div>
            </div>
            <div class="dash-tile">
                <span class="dash-tile__icon dash-tile__icon--danger"><i class="fas fa-hand-holding-usd"></i></span>
                <div class="dash-tile__label">Reste à encaisser</div>
                <div class="dash-tile__value">{{ $fcfa($outstandingAmount) }} <small>FCFA</small></div>
                <div class="dash-tile__hint">
                    @can('invoices.view')
                        <a href="{{ route('admin.invoices.index') }}">{{ $outstandingCount }} facture(s) non soldée(s)</a>
                    @else
                        {{ $outstandingCount }} facture(s) non soldée(s)
                    @endcan
                </div>
            </div>
        </div>
    @endcan

    {{-- ============================================================ Rooms & activity --}}
    <div class="dash-grid dash-grid--rooms">
        <div class="admin-card dash-occupancy mb-0">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h5 class="mb-1">Occupation des chambres</h5>
                    <div class="text-muted small">{{ $totalRooms }} chambre(s) au total</div>
                </div>
                <div class="dash-occupancy__rate">
                    {{ $occupancyRate }}<small>%</small>
                    <span>taux d'occupation</span>
                </div>
            </div>

            <div class="dash-meter" role="img" aria-label="Taux d'occupation {{ $occupancyRate }} %">
                <div class="dash-meter__fill" style="width: {{ $occupancyRate }}%"></div>
            </div>

            <div class="dash-stack mt-4" role="img" aria-label="Répartition des chambres par état">
                @foreach ($statusMeta as $key => [$label, $color])
                    @if ($roomCounts[$key] > 0)
                        <div
                            class="dash-stack__seg dash-stack__seg--{{ $color }}"
                            style="flex-grow: {{ $roomCounts[$key] }}"
                            tabindex="0"
                            data-tip="{{ $label }} : {{ $roomCounts[$key] }} chambre(s)"
                        ></div>
                    @endif
                @endforeach
            </div>

            <ul class="dash-legend">
                @foreach ($statusMeta as $key => [$label, $color, $filter])
                    <li>
                        <span class="dash-legend__swatch dash-legend__swatch--{{ $color }}"></span>
                        @can('rooms.view')
                            <a href="{{ route('admin.rooms.index', ['status' => $filter]) }}">{{ $label }}</a>
                        @else
                            {{ $label }}
                        @endcan
                        <strong>{{ $roomCounts[$key] }}</strong>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="dash-tiles-2x2">
            <div class="dash-tile">
                <span class="dash-tile__icon dash-tile__icon--success"><i class="fas fa-door-open"></i></span>
                <div class="dash-tile__label">Chambres libres</div>
                <div class="dash-tile__value">{{ $availableRooms }}<small> / {{ $totalRooms }}</small></div>
                <div class="dash-tile__hint">Prêtes à être affectées</div>
            </div>
            <div class="dash-tile">
                <span class="dash-tile__icon dash-tile__icon--danger"><i class="fas fa-bed"></i></span>
                <div class="dash-tile__label">Chambres occupées</div>
                <div class="dash-tile__value">{{ $occupiedRooms }}</div>
                <div class="dash-tile__hint">{{ $todayCheckOuts }} départ(s) prévu(s) aujourd'hui</div>
            </div>
            <div class="dash-tile">
                <span class="dash-tile__icon dash-tile__icon--info"><i class="fas fa-calendar-check"></i></span>
                <div class="dash-tile__label">Réservations à venir</div>
                <div class="dash-tile__value">{{ $upcomingReservations }}</div>
                <div class="dash-tile__hint">{{ $reservedRooms }} chambre(s) réservée(s) aujourd'hui</div>
            </div>
            <div class="dash-tile">
                <span class="dash-tile__icon dash-tile__icon--primary"><i class="fas fa-sign-in-alt"></i></span>
                <div class="dash-tile__label">Arrivées du jour</div>
                <div class="dash-tile__value">{{ $todayCheckIns }}</div>
                <div class="dash-tile__hint">Confirmées ou déjà installées</div>
            </div>
        </div>
    </div>

    {{-- ============================================================ Charts --}}
    @can('dashboard.revenue')
        @php
            $amounts = collect($dailyRevenue)->pluck('amount');
            $rawMax = max(1, $amounts->max());
            $magnitude = 10 ** floor(log10($rawMax));
            $niceMax = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($v) => $v >= $rawMax);
            $chart = ['w' => 640, 'h' => 220, 'left' => 64, 'right' => 8, 'top' => 18, 'bottom' => 30];
            $plotW = $chart['w'] - $chart['left'] - $chart['right'];
            $plotH = $chart['h'] - $chart['top'] - $chart['bottom'];
            $slot = $plotW / max(1, $amounts->count());
            $barW = min(24, $slot * 0.62);
            $maxIndex = $amounts->search($amounts->max());
            $modeMax = max(1, collect($revenueByMode)->max('amount'));
        @endphp

        <div class="dash-grid dash-grid--charts">
            <figure class="admin-card dash-chart mb-0">
                <figcaption>
                    <h5 class="mb-1">Recettes encaissées</h5>
                    <div class="text-muted small">{{ count($dailyRevenue) }} dernières journées commerciales · FCFA</div>
                </figcaption>

                <svg class="dash-columns" viewBox="0 0 {{ $chart['w'] }} {{ $chart['h'] }}" role="img" aria-label="Recettes encaissées par jour">
                    @foreach ([0, 0.5, 1] as $f)
                        @php
                            $y = $chart['top'] + $plotH * (1 - $f);
                        @endphp
                        <line x1="{{ $chart['left'] }}" x2="{{ $chart['w'] - $chart['right'] }}" y1="{{ $y }}" y2="{{ $y }}" class="dash-grid-line"></line>
                        <text x="{{ $chart['left'] - 10 }}" y="{{ $y + 4 }}" text-anchor="end" class="dash-axis">{{ $fcfa($niceMax * $f) }}</text>
                    @endforeach

                    @foreach ($dailyRevenue as $i => $point)
                        @php
                            $h = $point['amount'] > 0 ? max(4, $plotH * $point['amount'] / $niceMax) : 0;
                            $x = $chart['left'] + $slot * $i + ($slot - $barW) / 2;
                            $y = $chart['top'] + $plotH - $h;
                            $r = min(4, $h);
                            $isToday = $i === count($dailyRevenue) - 1;
                        @endphp
                        <g class="dash-col {{ $isToday ? 'is-current' : '' }}" tabindex="0"
                           data-tip="{{ $point['date']->translatedFormat('D j M') }} : {{ $fcfa($point['amount']) }} FCFA">
                            <rect class="dash-col__hit" x="{{ $chart['left'] + $slot * $i }}" y="{{ $chart['top'] }}" width="{{ $slot }}" height="{{ $plotH }}"></rect>
                            @if ($h > 0)
                                <path class="dash-col__bar"
                                      d="M{{ $x }},{{ $y + $h }} V{{ $y + $r }} Q{{ $x }},{{ $y }} {{ $x + $r }},{{ $y }} H{{ $x + $barW - $r }} Q{{ $x + $barW }},{{ $y }} {{ $x + $barW }},{{ $y + $r }} V{{ $y + $h }} Z"></path>
                            @endif
                            @if ($point['amount'] > 0 && $i === $maxIndex)
                                <text x="{{ $x + $barW / 2 }}" y="{{ $y - 6 }}" text-anchor="middle" class="dash-value">{{ $fcfa($point['amount']) }}</text>
                            @endif
                        </g>
                        @if ($i % 2 === (count($dailyRevenue) - 1) % 2)
                            <text x="{{ $chart['left'] + $slot * $i + $slot / 2 }}" y="{{ $chart['h'] - 8 }}" text-anchor="middle" class="dash-axis {{ $isToday ? 'is-current' : '' }}">{{ $isToday ? 'Auj.' : $point['date']->format('d/m') }}</text>
                        @endif
                    @endforeach
                    <line x1="{{ $chart['left'] }}" x2="{{ $chart['w'] - $chart['right'] }}" y1="{{ $chart['top'] + $plotH }}" y2="{{ $chart['top'] + $plotH }}" class="dash-baseline"></line>
                </svg>

                <details class="dash-data">
                    <summary>Voir les données</summary>
                    <table class="table table-sm">
                        <thead><tr><th>Journée</th><th class="text-end">Recettes (FCFA)</th></tr></thead>
                        <tbody>
                            @foreach (array_reverse($dailyRevenue) as $point)
                                <tr><td>{{ $point['date']->translatedFormat('l j F') }}</td><td class="text-end">{{ $fcfa($point['amount']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            </figure>

            <figure class="admin-card dash-chart mb-0">
                <figcaption>
                    <h5 class="mb-1">Chiffre d'affaires par formule</h5>
                    <div class="text-muted small">Ce mois · séjours facturés</div>
                </figcaption>

                <ul class="dash-hbars">
                    @foreach ($revenueByMode as $row)
                        <li tabindex="0" data-tip="{{ $row['mode']->getLabel() }} : {{ $fcfa($row['amount']) }} FCFA ({{ $row['count'] }} séjour(s))">
                            <div class="dash-hbars__label">
                                <span>{{ $row['mode']->getLabel() }}</span>
                                <span class="dash-hbars__value">{{ $fcfa($row['amount']) }} <small>FCFA · {{ $row['count'] }}</small></span>
                            </div>
                            <div class="dash-hbars__track">
                                <div class="dash-hbars__bar" style="width: {{ $row['amount'] > 0 ? max(2, $row['amount'] / $modeMax * 100) : 0 }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Cash received per payment method (this month, today in small). --}}
                <h6 class="mt-4 mb-2">Encaissé par mode de paiement <span class="text-muted small fw-normal">— ce mois (aujourd'hui)</span></h6>
                <ul class="dash-hbars">
                    @foreach ($cashByMethodMonth as $i => $row)
                        <li tabindex="0" data-tip="{{ $row['method']->getLabel() }} : {{ $fcfa($row['amount']) }} FCFA ce mois, {{ $fcfa($cashByMethodToday[$i]['amount']) }} FCFA aujourd'hui">
                            <div class="dash-hbars__label">
                                <span>{{ $row['method']->getLabel() }}</span>
                                <span class="dash-hbars__value">{{ $fcfa($row['amount']) }} <small>FCFA · {{ $row['share'] }} % ({{ $fcfa($cashByMethodToday[$i]['amount']) }} aujourd'hui)</small></span>
                            </div>
                            <div class="dash-hbars__track">
                                <div class="dash-hbars__bar" style="width: {{ $row['share'] }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </figure>
        </div>
    @endcan

    {{-- ============================================================ Lists --}}
    <div class="dash-grid dash-grid--lists">
        <div class="admin-card mb-0">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0"><i class="fas fa-sign-in-alt text-primary me-2"></i>Prochaines arrivées</h5>
                @can('reservations.view')
                    <a href="{{ route('admin.reservations.index') }}" class="small">Tout voir</a>
                @endcan
            </div>
            <table class="table align-middle">
                <thead><tr><th>Client</th><th>Chambre</th><th>Arrivée</th><th>Statut</th></tr></thead>
                <tbody>
                    @forelse ($upcomingArrivals as $reservation)
                        <tr>
                            <td>{{ $reservation->guest?->fullName() }}</td>
                            <td>N°{{ $reservation->room?->number }}</td>
                            <td>{{ $reservation->check_in->format('d/m H\hi') }}</td>
                            <td><span class="badge bg-{{ $reservation->status->getColor() }}">{{ $reservation->status->getLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Aucune arrivée prévue.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-card mb-0">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0"><i class="fas fa-sign-out-alt text-warning me-2"></i>Départs du jour</h5>
                @can('rooms.view')
                    <a href="{{ route('admin.rooms.index', ['status' => 'occupee']) }}" class="small">Chambres occupées</a>
                @endcan
            </div>
            <table class="table align-middle">
                <thead><tr><th>Client</th><th>Chambre</th><th>Départ prévu</th><th></th></tr></thead>
                <tbody>
                    @forelse ($todayDepartures as $stay)
                        <tr>
                            <td>{{ $stay->guest?->fullName() }}</td>
                            <td>N°{{ $stay->room?->number }}</td>
                            <td>{{ $stay->check_out->format('d/m H\hi') }}</td>
                            <td class="text-end">
                                @if ($stay->check_out->isPast())
                                    <span class="badge bg-danger">Dépassé</span>
                                @endif
                                @can('affectations.view')
                                    <a href="{{ route('admin.affectations.show', $stay) }}" class="btn btn-sm btn-outline-primary">Check-out</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Aucun départ prévu aujourd'hui.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
