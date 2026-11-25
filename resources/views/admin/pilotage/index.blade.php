@extends('admin.layouts.admin')

@section('title', 'Pilotage')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $variation = fn (float $now, float $before) => \App\Support\DepartmentRevenue::variation($now, $before);
        $badge = function (?float $v) {
            if ($v === null) {
                return '<span class="pilot-delta is-none">—</span>';
            }

            return '<span class="pilot-delta '.($v >= 0 ? 'is-up' : 'is-down').'">'.($v >= 0 ? '+' : '').number_format($v, 1, ',', ' ').' %</span>';
        };
        $range = fn ($a, $b) => $a->isSameDay($b) ? $a->format('d/m/Y') : $a->format('d/m/Y').' – '.$b->format('d/m/Y');
        $maxBucket = max(1, collect($evolution['buckets'])->max('total'));
        $period_query = $period === 'personnalisee' ? ['du' => $from->toDateString(), 'au' => $to->toDateString()] : ($period === 'jour' ? ['jour' => $from->toDateString()] : ['periode' => $period]);
    @endphp

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-chart-line text-primary me-2"></i>Pilotage</h1>
            <div class="dash-subtitle">Chiffre d'affaires encaissé de l'établissement, par service — {{ $range($from, $to) }}.</div>
        </div>
        <a href="{{ route('admin.pilotage.export', $period_query) }}" class="btn btn-outline-primary phone-hidden"><i class="far fa-file-excel"></i> Exporter (Excel)</a>
    </div>

    <form method="GET" action="{{ route('admin.pilotage.index') }}" class="admin-card">
        <div class="d-flex flex-wrap gap-2 mb-2">
            @foreach ($periods as $key => $label)
                <a href="{{ route('admin.pilotage.index', ['periode' => $key]) }}" class="btn btn-sm {{ $period === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label small" for="du">Du</label>
                <input type="date" class="form-control form-control-sm" id="du" name="du" value="{{ $from->toDateString() }}" max="{{ $today->toDateString() }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small" for="au">Au</label>
                <input type="date" class="form-control form-control-sm" id="au" name="au" value="{{ $to->toDateString() }}" max="{{ $today->toDateString() }}">
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="far fa-filter"></i> Afficher</button>
            </div>
        </div>
    </form>

    {{-- Global turnover and its comparisons. --}}
    <div class="pilot-kpis">
        <div class="admin-card pilot-kpi pilot-kpi--total">
            <div class="pilot-kpi__label">Chiffre d'affaires global</div>
            <div class="pilot-kpi__value">{{ $fcfa($total) }} <small>FCFA</small></div>
            <div class="pilot-kpi__compare">
                <span>vs période précédente ({{ $range($prevFrom, $prevTo) }}) : {!! $badge($variation($total, $totalPrevious)) !!}</span>
                <span>vs l'an dernier ({{ $range($lyFrom, $lyTo) }}) : {!! $badge($variation($total, $totalLastYear)) !!}</span>
            </div>
        </div>
        @foreach ($departments as $department)
            @php
                $amount = $current[$department->value]['amount'];
            @endphp
            <div class="admin-card pilot-kpi pilot-kpi--{{ $department->value }}">
                <div class="pilot-kpi__label"><i class="far {{ $department->icon() }}"></i> {{ $department->getLabel() }}</div>
                <div class="pilot-kpi__value">{{ $fcfa($amount) }} <small>FCFA</small></div>
                <div class="pilot-kpi__compare">
                    <span>{{ $total > 0 ? number_format($amount / $total * 100, 1, ',', ' ') : '0' }} % du total</span>
                    <span>vs période précédente : {!! $badge($variation($amount, $previous[$department->value]['amount'])) !!}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Comparison table and share of each service. --}}
    <div class="admin-card">
        <h5>Comparaison des services</h5>
        <div class="table-responsive">
            <table class="table align-middle mb-0 pilot-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th class="text-end">Encaissé</th>
                        <th style="min-width: 160px;">Part</th>
                        <th class="text-end">Encaissements</th>
                        <th class="text-end">Moyenne / encaissement</th>
                        <th class="text-end">Période précédente</th>
                        <th class="text-end">Évolution</th>
                        <th class="text-end">An dernier</th>
                        <th class="text-end">Évolution</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($departments as $department)
                        @php
                            $row = $current[$department->value];
                        @endphp
                        @php
                            $share = $total > 0 ? $row['amount'] / $total * 100 : 0;
                        @endphp
                        <tr>
                            <td class="text-nowrap"><span class="pilot-dot pilot-dot--{{ $department->value }}"></span> {{ $department->getLabel() }}</td>
                            <td class="text-end text-nowrap fw-semibold">{{ $fcfa($row['amount']) }} F</td>
                            <td>
                                <div class="pilot-bar" role="img" aria-label="{{ number_format($share, 1, ',', ' ') }} %">
                                    <div class="pilot-bar__fill pilot-fill--{{ $department->value }}" style="width: {{ round($share, 1) }}%"></div>
                                </div>
                                <span class="small text-muted">{{ number_format($share, 1, ',', ' ') }} %</span>
                            </td>
                            <td class="text-end">{{ $row['count'] }}</td>
                            <td class="text-end text-nowrap">{{ $row['count'] > 0 ? $fcfa($row['amount'] / $row['count']).' F' : '—' }}</td>
                            <td class="text-end text-nowrap">{{ $fcfa($previous[$department->value]['amount']) }} F</td>
                            <td class="text-end">{!! $badge($variation($row['amount'], $previous[$department->value]['amount'])) !!}</td>
                            <td class="text-end text-nowrap">{{ $fcfa($lastYear[$department->value]['amount']) }} F</td>
                            <td class="text-end">{!! $badge($variation($row['amount'], $lastYear[$department->value]['amount'])) !!}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td>Total</td>
                        <td class="text-end text-nowrap">{{ $fcfa($total) }} F</td>
                        <td></td>
                        <td class="text-end">{{ array_sum(array_column($current, 'count')) }}</td>
                        <td></td>
                        <td class="text-end text-nowrap">{{ $fcfa($totalPrevious) }} F</td>
                        <td class="text-end">{!! $badge($variation($total, $totalPrevious)) !!}</td>
                        <td class="text-end text-nowrap">{{ $fcfa($totalLastYear) }} F</td>
                        <td class="text-end">{!! $badge($variation($total, $totalLastYear)) !!}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Evolution: one stacked bar per day (or month), a colour per service. --}}
    <div class="admin-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h5 class="mb-0">Évolution {{ $evolution['granularity'] === 'mois' ? 'par mois' : 'par jour' }}</h5>
            <div class="pilot-legend">
                @foreach ($departments as $department)
                    <span><span class="pilot-dot pilot-dot--{{ $department->value }}"></span> {{ $department->getLabel() }}</span>
                @endforeach
            </div>
        </div>
        <div class="pilot-chart" role="img" aria-label="Évolution du chiffre d'affaires encaissé par service">
            @foreach ($evolution['buckets'] as $bucket)
                <div class="pilot-chart__col" title="{{ $bucket['label'] }} : {{ $fcfa($bucket['total']) }} F">
                    <div class="pilot-chart__stack" style="height: {{ round($bucket['total'] / $maxBucket * 100, 2) }}%">
                        @foreach ($departments as $department)
                            @if ($bucket['amounts'][$department->value] > 0)
                                <div class="pilot-fill--{{ $department->value }}" style="flex-grow: {{ $bucket['amounts'][$department->value] }}"></div>
                            @endif
                        @endforeach
                    </div>
                    <div class="pilot-chart__label">{{ $bucket['label'] }}</div>
                </div>
            @endforeach
        </div>
        <details class="mt-3">
            <summary class="small">Voir le détail chiffré</summary>
            <div class="table-responsive mt-2">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>{{ $evolution['granularity'] === 'mois' ? 'Mois' : 'Journée' }}</th>
                            @foreach ($departments as $department)<th class="text-end">{{ $department->getLabel() }}</th>@endforeach
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($evolution['buckets'] as $bucket)
                            <tr>
                                <td>{{ $bucket['label'] }}</td>
                                @foreach ($departments as $department)<td class="text-end text-nowrap">{{ $fcfa($bucket['amounts'][$department->value]) }}</td>@endforeach
                                <td class="text-end text-nowrap fw-semibold">{{ $fcfa($bucket['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </div>

    {{-- Activity of the sales services (by sale date). --}}
    @php
        $salesShown = array_filter(\App\Enums\Department::salesServices(), fn ($d) => in_array($d, $departments, true));
    @endphp
    @if ($salesShown !== [])
        <div class="admin-card">
            <h5>Activité des services</h5>
            <div class="row g-3">
                @foreach ($salesShown as $department)
                    @php
                        $a = $activity[$department->value];
                    @endphp
                    <div class="col-md-6">
                        <div class="pilot-activity">
                            <div class="fw-semibold mb-1"><i class="far {{ $department->icon() }}"></i> {{ $department->getLabel() }}</div>
                            <div class="d-flex flex-wrap gap-4">
                                <div><div class="small text-muted">Ventes</div><div class="h5 mb-0">{{ $a['sales'] }}</div></div>
                                <div><div class="small text-muted">Vendu</div><div class="h5 mb-0">{{ $fcfa($a['sold']) }} F</div></div>
                                <div><div class="small text-muted">Panier moyen</div><div class="h5 mb-0">{{ $fcfa($a['basket']) }} F</div></div>
                                @if ($department->countsEntries())
                                    <div><div class="small text-muted">Entrées</div><div class="h5 mb-0">{{ $a['entries'] }}</div></div>
                                @endif
                            </div>
                            @if (! $department->isOffered())
                                <span class="small text-muted">Service plus proposé (historique)</span>
                            @elseif ($department === \App\Enums\Department::Evenements)
                                @can('events.manage')
                                    <a href="{{ route('admin.events.index', ['onglet' => 'passees']) }}" class="small">Voir les réservations</a>
                                @endcan
                            @else
                                @can('sales.create')
                                    <a href="{{ route('admin.sales.index', $department->slug()) }}" class="small">Voir les ventes</a>
                                @endcan
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="small text-muted mt-2 mb-0">Ventes de la période (par date de vente, annulées exclues). « Vendu » peut différer de l'encaissé : ventes laissées dues ou reportées sur la chambre.</p>
        </div>
    @endif
@endsection
