@extends('admin.layouts.admin')

@section('title', 'Site web — Statistiques')

@section('content')
    @php
        $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $buckets = $series['buckets'];
        $values = collect($buckets)->pluck('visits');
        $rawMax = max(1, $values->max());
        $magnitude = 10 ** floor(log10($rawMax));
        $niceMax = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($v) => $v >= $rawMax);
        $chart = ['w' => 720, 'h' => 230, 'left' => 44, 'right' => 8, 'top' => 18, 'bottom' => 30];
        $plotW = $chart['w'] - $chart['left'] - $chart['right'];
        $plotH = $chart['h'] - $chart['top'] - $chart['bottom'];
        $slot = $plotW / max(1, count($buckets));
        $barW = min(24, $slot * 0.62);
        $maxIndex = $values->search($values->max());
        $labelEvery = count($buckets) > 16 ? 3 : 1;
        $sourceMax = max(1, collect($sources)->max('count'));
        $deviceMax = max(1, collect($devices)->max('count'));
        $pageMax = max(1, collect($pages)->max('views'));
        $kpiLabels = ['today' => ["Aujourd'hui", 'hier'], 'month' => ['Ce mois', 'même période du mois dernier'], 'year' => ['Cette année', "même période l'an dernier"]];
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>Statistiques du site</h1>
            <div class="dash-subtitle">
                Fréquentation du site web, mesurée sans cookie : une <strong>visite</strong> = un visiteur sur une journée (quel que soit le nombre de pages vues), les <strong>pages vues</strong> comptent chaque page.
                Les robots et le personnel connecté ne sont pas comptés.
            </div>
        </div>
    </div>

    {{-- Today / this month / this year --}}
    <div class="dash-grid dash-grid--money mb-4 site-stats-kpis">
        @foreach ($kpis as $key => $kpi)
            <div class="dash-tile">
                <span class="dash-tile__icon dash-tile__icon--primary"><i class="fas {{ $key === 'today' ? 'fa-eye' : ($key === 'month' ? 'fa-calendar-alt' : 'fa-chart-line') }}"></i></span>
                <div class="dash-tile__label">{{ $kpiLabels[$key][0] }}</div>
                <div class="dash-tile__value">{{ $n($kpi['visits']) }} <small>visite{{ $kpi['visits'] > 1 ? 's' : '' }}</small></div>
                <div class="dash-tile__hint">
                    {{ $n($kpi['views']) }} page{{ $kpi['views'] > 1 ? 's' : '' }} vue{{ $kpi['views'] > 1 ? 's' : '' }}
                    @if ($kpi['delta'] !== null)
                        · <span class="dash-delta {{ $kpi['delta'] >= 0 ? 'is-up' : 'is-down' }}"><i class="fas {{ $kpi['delta'] >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i> {{ $kpi['delta'] >= 0 ? '+' : '' }}{{ $kpi['delta'] }} %</span> vs {{ $kpiLabels[$key][1] }}
                    @elseif ($kpi['previous'] === 0 && $kpi['visits'] > 0)
                        · aucune visite {{ $kpiLabels[$key][1] }}
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach (\App\Support\SiteStats::PERIODS as $key => [$label])
            <li class="nav-item"><a href="{{ route('admin.site.stats', ['periode' => $key]) }}" class="nav-link {{ $period === $key ? 'active' : '' }}">{{ $label }}</a></li>
        @endforeach
    </ul>

    <figure class="admin-card dash-chart mb-4">
        <figcaption class="d-flex justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Visites</h5>
                <div class="text-muted small">{{ \App\Support\SiteStats::PERIODS[$period][0] }} · du {{ $series['from']->format('d/m/Y') }} au {{ $series['to']->format('d/m/Y') }}</div>
            </div>
            <div class="text-end">
                <strong class="fs-5">{{ $n($totals['visits']) }}</strong> <span class="text-muted">visites</span>
                <div class="small text-muted">{{ $n($totals['views']) }} pages vues @if ($totals['visits'] > 0) · {{ number_format($totals['views'] / $totals['visits'], 1, ',', ' ') }} pages par visite @endif</div>
            </div>
        </figcaption>

        <svg class="dash-columns" viewBox="0 0 {{ $chart['w'] }} {{ $chart['h'] }}" role="img" aria-label="Visites du site par {{ $period === 'jours' ? 'jour' : ($period === 'mois' ? 'mois' : 'année') }}">
            @foreach ([0, 0.5, 1] as $f)
                @php
                    $y = $chart['top'] + $plotH * (1 - $f);
                @endphp
                <line x1="{{ $chart['left'] }}" x2="{{ $chart['w'] - $chart['right'] }}" y1="{{ $y }}" y2="{{ $y }}" class="dash-grid-line"></line>
                <text x="{{ $chart['left'] - 8 }}" y="{{ $y + 4 }}" text-anchor="end" class="dash-axis">{{ $n($niceMax * $f) }}</text>
            @endforeach
            @foreach ($buckets as $i => $point)
                @php
                    $h = $point['visits'] > 0 ? max(4, $plotH * $point['visits'] / $niceMax) : 0;
                    $x = $chart['left'] + $slot * $i + ($slot - $barW) / 2;
                    $y = $chart['top'] + $plotH - $h;
                    $r = min(4, $h);
                    $isCurrent = $i === count($buckets) - 1;
                @endphp
                <g class="dash-col {{ $isCurrent ? 'is-current' : '' }}" tabindex="0" data-tip="{{ $point['label'] }} : {{ $n($point['visits']) }} visite(s), {{ $n($point['views']) }} page(s) vue(s)">
                    <rect class="dash-col__hit" x="{{ $chart['left'] + $slot * $i }}" y="{{ $chart['top'] }}" width="{{ $slot }}" height="{{ $plotH }}"></rect>
                    @if ($h > 0)
                        <path class="dash-col__bar" d="M{{ $x }},{{ $y + $h }} V{{ $y + $r }} Q{{ $x }},{{ $y }} {{ $x + $r }},{{ $y }} H{{ $x + $barW - $r }} Q{{ $x + $barW }},{{ $y }} {{ $x + $barW }},{{ $y + $r }} V{{ $y + $h }} Z"></path>
                    @endif
                    @if ($point['visits'] > 0 && $i === $maxIndex)
                        <text x="{{ $x + $barW / 2 }}" y="{{ $y - 6 }}" text-anchor="middle" class="dash-value">{{ $n($point['visits']) }}</text>
                    @endif
                </g>
                @if (($i - (count($buckets) - 1)) % $labelEvery === 0)
                    <text x="{{ $chart['left'] + $slot * $i + $slot / 2 }}" y="{{ $chart['h'] - 8 }}" text-anchor="middle" class="dash-axis {{ $isCurrent ? 'is-current' : '' }}">{{ $point['short'] }}</text>
                @endif
            @endforeach
            <line x1="{{ $chart['left'] }}" x2="{{ $chart['w'] - $chart['right'] }}" y1="{{ $chart['top'] + $plotH }}" y2="{{ $chart['top'] + $plotH }}" class="dash-baseline"></line>
        </svg>

        <details class="dash-data">
            <summary>Voir les données</summary>
            <table class="table table-sm">
                <thead><tr><th>Période</th><th class="text-end">Visites</th><th class="text-end">Pages vues</th></tr></thead>
                <tbody>
                    @foreach (array_reverse($buckets) as $point)
                        <tr><td>{{ $point['label'] }}</td><td class="text-end">{{ $n($point['visits']) }}</td><td class="text-end">{{ $n($point['views']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    </figure>

    <div class="dash-grid dash-grid--charts mb-4">
        <figure class="admin-card dash-chart mb-0">
            <figcaption><h5 class="mb-1">D'où viennent les visiteurs</h5><div class="text-muted small">Visites sur la période</div></figcaption>
            @forelse ($sources as $row)
                @if ($loop->first)<ul class="dash-hbars">@endif
                <li tabindex="0" data-tip="{{ $row['label'] }} : {{ $n($row['count']) }} visite(s), {{ $row['share'] }} %">
                    <div class="dash-hbars__label"><span>{{ $row['label'] }}</span><span class="dash-hbars__value">{{ $n($row['count']) }} <small>· {{ $row['share'] }} %</small></span></div>
                    <div class="dash-hbars__track"><div class="dash-hbars__bar" style="width: {{ max(2, $row['count'] / $sourceMax * 100) }}%"></div></div>
                </li>
                @if ($loop->last)</ul>@endif
            @empty
                <p class="text-muted mb-0">Aucune visite sur la période.</p>
            @endforelse
            @if ($referrers)
                <h6 class="mt-4 mb-2">Sites qui envoient des visiteurs</h6>
                <ul class="list-unstyled small mb-0">
                    @foreach ($referrers as $ref)
                        <li class="d-flex justify-content-between border-bottom py-1"><span>{{ $ref['host'] }}</span><span>{{ $n($ref['count']) }}</span></li>
                    @endforeach
                </ul>
            @endif

            <h6 class="mt-4 mb-2">Appareils</h6>
            @forelse ($devices as $row)
                @if ($loop->first)<ul class="dash-hbars">@endif
                <li tabindex="0" data-tip="{{ $row['label'] }} : {{ $n($row['count']) }} visite(s), {{ $row['share'] }} %">
                    <div class="dash-hbars__label"><span>{{ $row['label'] }}</span><span class="dash-hbars__value">{{ $n($row['count']) }} <small>· {{ $row['share'] }} %</small></span></div>
                    <div class="dash-hbars__track"><div class="dash-hbars__bar" style="width: {{ max(2, $row['count'] / $deviceMax * 100) }}%"></div></div>
                </li>
                @if ($loop->last)</ul>@endif
            @empty
                <p class="text-muted mb-0">—</p>
            @endforelse
        </figure>

        <figure class="admin-card dash-chart mb-0">
            <figcaption><h5 class="mb-1">Pages les plus vues</h5><div class="text-muted small">Pages vues sur la période (visiteurs)</div></figcaption>
            @forelse ($pages as $row)
                @if ($loop->first)<ul class="dash-hbars">@endif
                <li tabindex="0" data-tip="{{ $row['label'] }} : {{ $n($row['views']) }} vue(s) par {{ $n($row['visitors']) }} visiteur(s)">
                    <div class="dash-hbars__label"><span>{{ $row['label'] }}</span><span class="dash-hbars__value">{{ $n($row['views']) }} <small>({{ $n($row['visitors']) }})</small></span></div>
                    <div class="dash-hbars__track"><div class="dash-hbars__bar" style="width: {{ max(2, $row['views'] / $pageMax * 100) }}%"></div></div>
                </li>
                @if ($loop->last)</ul>@endif
            @empty
                <p class="text-muted mb-0">Aucune page vue sur la période.</p>
            @endforelse

            <h6 class="mt-4 mb-2">Ce que les visiteurs ont fait</h6>
            <table class="table table-sm mb-0 site-stats-conv">
                <tbody>
                    <tr>
                        <td>Demandes de réservation en ligne</td>
                        <td class="text-end"><strong>{{ $n($conversions['booking_requests']) }}</strong></td>
                    </tr>
                    <tr class="text-muted small">
                        <td class="ps-3">dont devenues réservations</td>
                        <td class="text-end">{{ $n($conversions['booking_handled']) }}</td>
                    </tr>
                    <tr class="text-muted small">
                        <td class="ps-3">vues du formulaire « Réserver »</td>
                        <td class="text-end">{{ $n($conversions['booking_page_views']) }}@if ($totals['visits'] > 0 && $conversions['booking_requests'] > 0) · {{ number_format($conversions['booking_requests'] * 100 / $totals['visits'], 1, ',', ' ') }} % des visites @endif</td>
                    </tr>
                    <tr>
                        <td>Demandes sur place (QR code)</td>
                        <td class="text-end"><strong>{{ $n($conversions['stay_requests']) }}</strong></td>
                    </tr>
                    <tr class="text-muted small">
                        <td class="ps-3">dont validées</td>
                        <td class="text-end">{{ $n($conversions['stay_validated']) }}</td>
                    </tr>
                    <tr class="text-muted small">
                        <td class="ps-3">scans du QR code (page ouverte)</td>
                        <td class="text-end">{{ $n($conversions['qr_page_views']) }}</td>
                    </tr>
                </tbody>
            </table>
        </figure>
    </div>
@endsection
