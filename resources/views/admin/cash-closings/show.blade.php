@extends('admin.layouts.admin')

@section('title', 'Clôture du '.$closing->business_date->format('d/m/Y'))
@section('sweetalert-confirm', '1')

@section('content')
    @php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))
    @php($gaps = $closing->differences())

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-cash-register text-primary me-2"></i>Clôture du {{ $closing->business_date->format('d/m/Y') }}</h1>
            <div class="dash-subtitle">Caisse de <strong>{{ $closing->user?->name }}</strong> · <span class="badge bg-{{ $closing->statusColor() }}">{{ $closing->statusLabel() }}</span></div>
        </div>
        <a href="{{ route('admin.cash-closings.index') }}" class="btn btn-outline-secondary"><i class="far fa-arrow-left"></i> Clôtures</a>
    </div>

    @if ($figuresChanged)
        <div class="alert alert-warning">
            <i class="far fa-exclamation-triangle me-1"></i> Les chiffres de cette journée ont changé depuis la clôture (encaissement ou dépense ajouté, supprimé ou validé ensuite).
            Attendu aujourd'hui : <strong>{{ $fcfa(array_sum(array_column($current, 'expected'))) }} F</strong> au lieu de {{ $fcfa($closing->total_expected) }} F.
        </div>
    @endif

    <div class="admin-card">
        <table class="table align-middle table-keep">
            <thead><tr><th>Moyen</th><th class="text-end">Encaissé</th><th class="text-end">Dépenses</th><th class="text-end">Attendu</th><th class="text-end">Remis</th><th class="text-end">Écart</th></tr></thead>
            <tbody>
                @foreach (\App\Enums\PaymentMethod::cases() as $m)
                    @php($row = $closing->expected[$m->value] ?? ['received' => 0, 'expenses' => 0, 'expected' => 0])
                    @php($gap = $gaps[$m->value])
                    <tr>
                        <th scope="row"><i class="fas {{ $m->icon() }} me-1"></i> {{ $m->getLabel() }}</th>
                        <td class="text-end">{{ $fcfa($row['received']) }} F</td>
                        <td class="text-end">{{ $row['expenses'] > 0 ? '− '.$fcfa($row['expenses']).' F' : '—' }}</td>
                        <td class="text-end fw-semibold">{{ $fcfa($row['expected']) }} F</td>
                        <td class="text-end">{{ $fcfa($closing->declared[$m->value] ?? 0) }} F</td>
                        <td class="text-end fw-semibold {{ $gap < 0 ? 'text-danger' : ($gap > 0 ? 'text-warning' : 'text-success') }}">{{ $gap > 0 ? '+' : '' }}{{ $fcfa($gap) }} F</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <th>Total</th><td></td><td></td>
                    <td class="text-end">{{ $fcfa($closing->total_expected) }} F</td>
                    <td class="text-end">{{ $fcfa($closing->total_declared) }} F</td>
                    <td class="text-end {{ (float) $closing->total_difference < 0 ? 'text-danger' : ((float) $closing->total_difference > 0 ? 'text-warning' : 'text-success') }}">{{ (float) $closing->total_difference > 0 ? '+' : '' }}{{ $fcfa($closing->total_difference) }} F</td>
                </tr>
            </tfoot>
        </table>
        <p class="small text-muted mb-1">Un écart négatif est un manque, un écart positif un surplus.</p>

        @if ($closing->counted)
            <details class="mt-2">
                <summary>Billetage déclaré ({{ $fcfa(\App\Models\CashClosing::countedTotal($closing->counted)) }} F)</summary>
                <ul class="small mb-0 mt-1">
                    @foreach (['notes' => 'Billet', 'coins' => 'Pièce'] as $kind => $label)
                        @foreach ($closing->counted[$kind] ?? [] as $value => $n)
                            <li>{{ $n }} × {{ strtolower($label) }} de {{ $fcfa($value) }} = {{ $fcfa($n * $value) }} F</li>
                        @endforeach
                    @endforeach
                </ul>
            </details>
        @endif

        <dl class="row mt-3 mb-0 small">
            <dt class="col-sm-3">Déclarée par</dt><dd class="col-sm-9">{{ $closing->submitter?->name ?? '—' }} le {{ $closing->submitted_at?->format('d/m/Y H\hi') }}</dd>
            <dt class="col-sm-3">Commentaire</dt><dd class="col-sm-9">{{ $closing->comment ?: '—' }}</dd>
            @if ($closing->reviewed_at)
                <dt class="col-sm-3">{{ $closing->status === \App\Models\CashClosing::STATUS_VALIDATED ? 'Validée par' : 'Contestée par' }}</dt>
                <dd class="col-sm-9">{{ $closing->reviewer?->name ?? '—' }} le {{ $closing->reviewed_at->format('d/m/Y H\hi') }}@if ($closing->review_comment)<div>« {{ $closing->review_comment }} »</div>@endif</dd>
            @endif
        </dl>

        @if ($canReview && $closing->status === \App\Models\CashClosing::STATUS_SUBMITTED)
            <hr>
            <div class="row g-2">
                <div class="col-md-6">
                    <form method="POST" action="{{ route('admin.cash-closings.validate', $closing) }}" class="d-flex gap-2" data-confirm="Valider la clôture du {{ $closing->business_date->format('d/m/Y') }} ({{ $closing->user?->name }}) ?" data-confirm-title="Valider la clôture" data-confirm-button="Valider">
                        @csrf
                        <input type="text" name="review_comment" class="form-control" maxlength="1000" placeholder="Commentaire (facultatif)">
                        <button type="submit" class="btn btn-success text-nowrap"><i class="far fa-check"></i> Valider</button>
                    </form>
                </div>
                <div class="col-md-6">
                    <form method="POST" action="{{ route('admin.cash-closings.contest', $closing) }}" class="d-flex gap-2" data-confirm="Contester la clôture du {{ $closing->business_date->format('d/m/Y') }} ?" data-confirm-title="Contester la clôture" data-confirm-button="Contester" data-confirm-danger>
                        @csrf
                        <input type="text" name="review_comment" class="form-control @error('review_comment') is-invalid @enderror" maxlength="1000" placeholder="Motif (obligatoire)" required>
                        <button type="submit" class="btn btn-outline-danger text-nowrap"><i class="far fa-times"></i> Contester</button>
                    </form>
                </div>
            </div>
        @endif
        @if (! $canReview && $closing->status !== \App\Models\CashClosing::STATUS_VALIDATED && $closing->business_date->greaterThanOrEqualTo(\App\Support\BusinessDay::today()->subDays(\App\Http\Requests\Admin\CashClosingRequest::DAYS_BACK)))
            <hr>
            <a href="{{ route('admin.cash-closings.index', ['jour' => $closing->business_date->format('Y-m-d')]) }}" class="btn btn-outline-primary"><i class="far fa-redo"></i> Déclarer à nouveau</a>
        @endif
    </div>
@endsection
