{{-- « Ma recette du jour »: what the logged-in staff member cashed during the business day
     (FrontDeskController::myCash()). Shown to everyone who records payments. --}}
@php
    $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp
<section class="my-cash" aria-label="Ma recette du jour">
    <div class="my-cash__main">
        <span class="my-cash__label"><i class="fas fa-cash-register"></i> Ma recette du jour</span>
        <span class="my-cash__value">{{ $fcfa($myCash['total']) }} <small>FCFA</small></span>
        <span class="my-cash__hint">
            {{ $myCash['count'] }} encaissement{{ $myCash['count'] > 1 ? 's' : '' }} enregistré{{ $myCash['count'] > 1 ? 's' : '' }} par vous
            depuis {{ \App\Support\BusinessDay::current()[0]->format('H\hi') }}
        </span>
        <span class="my-cash__booking"><i class="fas fa-calendar-alt"></i> dont réservations : <strong>{{ $fcfa($myCash['booking']) }} F</strong></span>
    </div>
    <ul class="my-cash__methods">
        @foreach ($myCash['byMethod'] as $row)
            <li class="{{ $row['amount'] > 0 ? '' : 'is-empty' }}">
                <span><i class="fas {{ $row['method']->icon() }}"></i> {{ $row['method']->getLabel() }}</span>
                <strong>{{ $fcfa($row['amount']) }} F</strong>
            </li>
        @endforeach
    </ul>
    <div class="my-cash__side">
        @if ($myCash['cash'] > 0)
            <div class="my-cash__drawer"><i class="far fa-wallet"></i> Espèces à remettre : <strong>{{ $fcfa($myCash['cash']) }} F</strong></div>
        @endif
        <a href="{{ route('admin.revenue.index') }}" class="btn btn-sm btn-light fw-semibold">Voir le détail</a>
    </div>
</section>
