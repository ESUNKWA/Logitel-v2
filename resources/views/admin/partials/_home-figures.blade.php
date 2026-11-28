{{-- Home « figures band »: occupancy for rooms.view, the hotel's money for dashboard.revenue.
     Rendered by the home page and re-rendered by LiveFiguresController (live refresh). --}}
@if ($finance || $roomCounts || ($dueToday ?? null))
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $occupied = $roomCounts['occupee'] ?? 0;
        $sellable = $roomCounts ? array_sum($roomCounts) - ($roomCounts['hors_service'] ?? 0) : 0;
        $occupancy = $sellable > 0 ? (int) round($occupied / $sellable * 100) : 0;
    @endphp
    <section class="home-stats" aria-label="Chiffres du jour">
        @if ($finance)
            <div class="home-stat home-stat--primary">
                <span class="home-stat__label"><i class="fas fa-coins"></i> Recette du jour</span>
                <span class="home-stat__value">{{ $fcfa($finance['today']) }} <small>FCFA</small></span>
                <span class="home-stat__hint">
                    {{ $finance['todayCount'] }} encaissement(s)
                    @if ($finance['discountsToday'] > 0)
                        · {{ $fcfa($finance['discountsToday']) }} F de remises
                    @endif
                </span>
                <span class="home-stat__booking"><i class="fas fa-calendar-alt"></i> dont réservations : <strong>{{ $fcfa($finance['todayBooking']) }} F</strong></span>
            </div>
        @endif
        @if ($roomCounts)
            <div class="home-stat">
                <span class="home-stat__label"><i class="fas fa-bed"></i> Chambres occupées</span>
                <span class="home-stat__value">{{ $occupied }} <small>/ {{ $sellable }}</small></span>
                <span class="home-stat__hint">Taux d'occupation {{ $occupancy }} % · {{ $roomCounts['disponible'] ?? 0 }} libre(s)</span>
            </div>
        @endif
        @if ($dueToday ?? null)
            {{-- Réception (client request, 2026-11-28): only what is due by the end of the business day
                 (FinanceStats::outstandingDueBy) — future bookings and events are left out. --}}
            @php
                $dueAmount = $dueToday[0];
                $dueCount = $dueToday[1];
            @endphp
            <a href="{{ route('admin.front-desk.collect', ['periode' => 'aujourdhui']) }}" class="home-stat {{ $dueAmount > 0 ? 'home-stat--danger' : '' }}" data-due-today>
                <span class="home-stat__label"><i class="fas fa-hand-holding-usd"></i> Reste à encaisser</span>
                <span class="home-stat__value">{{ $fcfa($dueAmount) }} <small>FCFA</small></span>
                <span class="home-stat__hint">{{ $dueCount ? $dueCount.' facture'.($dueCount > 1 ? 's' : '').' à régler aujourd\'hui' : 'Rien à encaisser aujourd\'hui' }}</span>
            </a>
        @endif
        @if ($finance)
            <div class="home-stat">
                <span class="home-stat__label"><i class="fas fa-calendar-check"></i> Recette du mois</span>
                <span class="home-stat__value">{{ $fcfa($finance['month']) }} <small>FCFA</small></span>
                <span class="home-stat__hint">Encaissé depuis le début du mois</span>
                <span class="home-stat__booking"><i class="fas fa-calendar-alt"></i> dont réservations : <strong>{{ $fcfa($finance['monthBooking']) }} F</strong></span>
            </div>
            <a href="{{ route('admin.front-desk.collect') }}" class="home-stat {{ $finance['outstanding'] > 0 ? 'home-stat--danger' : '' }}">
                <span class="home-stat__label"><i class="fas fa-hand-holding-usd"></i> Reste à encaisser</span>
                <span class="home-stat__value">{{ $fcfa($finance['outstanding']) }} <small>FCFA</small></span>
                <span class="home-stat__hint">{{ $finance['outstandingCount'] }} facture(s) non soldée(s)</span>
            </a>

            <div class="home-methods">
                <div class="home-methods__title">Recette du jour par mode de paiement</div>
                @foreach ($finance['byMethod'] as $row)
                    <div class="home-methods__row">
                        <span class="home-methods__name"><i class="fas {{ $row['method']->icon() }}"></i> {{ $row['method']->getLabel() }}</span>
                        <span class="home-methods__bar" role="img" aria-label="{{ $row['share'] }} %"><span style="width: {{ $row['share'] }}%"></span></span>
                        <span class="home-methods__amount">{{ $fcfa($row['amount']) }} F <small>{{ $row['share'] }} %</small></span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endif
