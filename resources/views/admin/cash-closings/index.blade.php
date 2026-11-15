@extends('admin.layouts.admin')

@section('title', 'Clôture de caisse')
@section('sweetalert-confirm', '1')

@section('content')
    @php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))
    @php($methods = \App\Enums\PaymentMethod::cases())

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-cash-register text-primary me-2"></i>Clôture de caisse</h1>
            <div class="dash-subtitle">En fin de journée, déclarez ce que vous remettez pour chaque moyen de paiement. L'application compare avec ce qui a été enregistré.</div>
        </div>
    </div>

    @if ($canClose)
        <div class="admin-card cash-close">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h5 class="mb-0">Ma caisse du {{ $day->translatedFormat('l d/m/Y') }}</h5>
                <form method="GET" action="{{ route('admin.cash-closings.index') }}" class="d-flex gap-2 align-items-center">
                    <label class="small text-muted" for="jour">Journée</label>
                    <input type="date" class="form-control form-control-sm" id="jour" name="jour" value="{{ $day->format('Y-m-d') }}"
                        min="{{ $today->copy()->subDays(\App\Http\Requests\Admin\CashClosingRequest::DAYS_BACK)->format('Y-m-d') }}" max="{{ $today->format('Y-m-d') }}" onchange="this.form.submit()">
                    <noscript><button class="btn btn-sm btn-outline-primary">Voir</button></noscript>
                </form>
            </div>

            @if ($closing)
                <div class="alert alert-{{ $closing->statusColor() }} py-2">
                    <i class="far fa-info-circle me-1"></i>
                    Clôture déjà déclarée le {{ $closing->submitted_at?->format('d/m/Y H\hi') }} — <strong>{{ $closing->statusLabel() }}</strong>.
                    <a href="{{ route('admin.cash-closings.show', $closing) }}">Voir le détail</a>
                    @if ($closing->status === \App\Models\CashClosing::STATUS_CONTESTED && $closing->review_comment)
                        <div class="small mt-1">Motif du gérant : {{ $closing->review_comment }}</div>
                    @endif
                    @if ($closing->status !== \App\Models\CashClosing::STATUS_VALIDATED)
                        <div class="small mt-1">Vous pouvez la déclarer à nouveau ci-dessous.</div>
                    @endif
                </div>
            @endif

            @if ($pendingExpenses > 0)
                <div class="alert alert-warning py-2 small"><i class="far fa-hourglass-half me-1"></i> {{ $fcfa($pendingExpenses) }} F de dépenses payées en caisse ce jour attendent encore la validation du gérant : elles ne sont pas déduites tant qu'elles ne sont pas validées.</div>
            @endif

            @unless ($closing && $closing->status === \App\Models\CashClosing::STATUS_VALIDATED)
                <form method="POST" action="{{ route('admin.cash-closings.store') }}" data-cash-closing
                    data-confirm="Déclarer la clôture de votre caisse du {{ $day->format('d/m/Y') }} ?" data-confirm-title="Clôturer ma caisse" data-confirm-button="Clôturer">
                    @csrf
                    <input type="hidden" name="jour" value="{{ $day->format('Y-m-d') }}">
                    <table class="table align-middle table-keep cash-close__table">
                        <thead>
                            <tr><th>Moyen</th><th class="text-end">Encaissé</th><th class="text-end">Dépenses</th><th class="text-end">Attendu</th><th style="min-width: 9rem">Je remets</th><th class="text-end">Écart</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($methods as $m)
                                @php($row = $expected[$m->value])
                                <tr data-cash-row data-expected="{{ $row['expected'] }}">
                                    <th scope="row"><i class="fas {{ $m->icon() }} me-1"></i> {{ $m->getLabel() }}</th>
                                    <td class="text-end" data-label="Encaissé">{{ $fcfa($row['received']) }} F</td>
                                    <td class="text-end" data-label="Dépenses">{{ $row['expenses'] > 0 ? '− '.$fcfa($row['expenses']).' F' : '—' }}</td>
                                    <td class="text-end fw-semibold" data-label="Attendu">{{ $fcfa($row['expected']) }} F</td>
                                    <td data-label="Je remets">
                                        <input type="text" inputmode="numeric" data-money class="form-control form-control-sm text-end @error('declared.'.$m->value) is-invalid @enderror"
                                            name="declared[{{ $m->value }}]" id="declared-{{ $m->value }}" value="{{ old('declared.'.$m->value, $closing?->declared[$m->value] ?? '') }}"
                                            required data-cash-declared @if ($m === \App\Enums\PaymentMethod::Especes) data-cash-especes @endif aria-label="{{ $m->getLabel() }} remis">
                                        @error('declared.'.$m->value)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </td>
                                    <td class="text-end fw-semibold" data-label="Écart" data-cash-gap>—</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th><td></td><td></td>
                                <td class="text-end fw-bold">{{ $fcfa(array_sum(array_column($expected, 'expected'))) }} F</td>
                                <td class="text-end fw-bold" data-cash-total-declared>—</td>
                                <td class="text-end fw-bold" data-cash-total-gap>—</td>
                            </tr>
                        </tfoot>
                    </table>

                    <details class="cash-count mb-3" @if (old('counted') || $closing?->counted) open @endif>
                        <summary><i class="far fa-coins me-1"></i> Compter les espèces billet par billet (facultatif)</summary>
                        <p class="small text-muted mt-2 mb-2">Le total des billets et pièces remplace le montant des espèces.</p>
                        <div class="row g-2">
                            @foreach (['notes' => \App\Models\CashClosing::NOTES, 'coins' => \App\Models\CashClosing::COINS] as $kind => $values)
                                @foreach ($values as $value)
                                    <div class="col-6 col-md-3 col-xl-2">
                                        <label class="form-label small mb-0" for="count-{{ $kind }}-{{ $value }}">{{ $kind === 'notes' ? 'Billet' : 'Pièce' }} de {{ $fcfa($value) }}</label>
                                        <input type="number" min="0" max="100000" step="1" class="form-control form-control-sm" id="count-{{ $kind }}-{{ $value }}"
                                            name="counted[{{ $kind }}][{{ $value }}]" value="{{ old('counted.'.$kind.'.'.$value, $closing?->counted[$kind][$value] ?? '') }}" data-cash-count="{{ $value }}">
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                        <div class="small mt-2">Total compté : <strong data-cash-counted>0 F</strong></div>
                    </details>

                    <div class="mb-3">
                        <label class="form-label" for="comment">Commentaire <span class="text-muted small">(obligatoire s'il y a un écart)</span></label>
                        <textarea class="form-control @error('comment') is-invalid @enderror" id="comment" name="comment" rows="2" maxlength="1000" placeholder="Ex. 2 000 F rendus en trop à un client…">{{ old('comment', $closing?->comment) }}</textarea>
                        @error('comment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @error('jour')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                    @error('closing')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                    <button type="submit" class="btn btn-primary"><i class="far fa-lock"></i> {{ $closing ? 'Déclarer à nouveau' : 'Clôturer ma caisse' }}</button>
                </form>
            @endunless
        </div>

        @if ($history->isNotEmpty())
            <div class="admin-card">
                <h5>Mes clôtures</h5>
                <table class="table align-middle">
                    <thead><tr><th>Journée</th><th class="text-end">Attendu</th><th class="text-end">Remis</th><th class="text-end" data-card-title>Écart</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($history as $c)
                            <tr>
                                <td data-order="{{ $c->business_date->format('Ymd') }}">{{ $c->business_date->format('d/m/Y') }}</td>
                                <td class="text-end">{{ $fcfa($c->total_expected) }} F</td>
                                <td class="text-end">{{ $fcfa($c->total_declared) }} F</td>
                                <td class="text-end fw-semibold {{ (float) $c->total_difference < 0 ? 'text-danger' : ((float) $c->total_difference > 0 ? 'text-warning' : 'text-success') }}">{{ (float) $c->total_difference > 0 ? '+' : '' }}{{ $fcfa($c->total_difference) }} F</td>
                                <td><span class="badge bg-{{ $c->statusColor() }}">{{ $c->statusLabel() }}</span></td>
                                <td class="text-end"><a href="{{ route('admin.cash-closings.show', $c) }}" class="btn btn-sm btn-outline-primary">Voir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    @if ($canReview)
        <div class="admin-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h5 class="mb-0">Clôtures du personnel</h5>
                <div class="small">
                    <span class="badge {{ $toReview ? 'bg-warning text-dark' : 'bg-light text-dark' }}">{{ $toReview }} à valider</span>
                    <span class="ms-2">Écart cumulé du mois : <strong class="{{ $monthGap < 0 ? 'text-danger' : '' }}">{{ $monthGap > 0 ? '+' : '' }}{{ $fcfa($monthGap) }} F</strong></span>
                </div>
            </div>
            <form method="GET" action="{{ route('admin.cash-closings.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-6 col-md-2"><label class="form-label small" for="du">Du</label><input type="date" class="form-control form-control-sm" id="du" name="du" value="{{ $filters['from']->format('Y-m-d') }}"></div>
                <div class="col-6 col-md-2"><label class="form-label small" for="au">Au</label><input type="date" class="form-control form-control-sm" id="au" name="au" value="{{ $filters['to']->format('Y-m-d') }}"></div>
                <div class="col-6 col-md-3">
                    <label class="form-label small" for="par">Réceptionniste</label>
                    <select class="form-select form-select-sm" id="par" name="par">
                        <option value="">Toutes</option>
                        @foreach ($staff as $u)
                            <option value="{{ $u->id }}" @selected($filters['user'] === $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small" for="statut">Statut</label>
                    <select class="form-select form-select-sm" id="statut" name="statut">
                        <option value="">Tous</option>
                        @foreach (\App\Models\CashClosing::STATUSES as $key => $s)
                            <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $s['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="far fa-filter"></i> Filtrer</button>
                    <a href="{{ route('admin.cash-closings.export', ['du' => $filters['from']->format('Y-m-d'), 'au' => $filters['to']->format('Y-m-d'), 'par' => $filters['user'], 'statut' => $filters['status']]) }}" class="btn btn-outline-primary btn-sm phone-hidden"><i class="far fa-file-excel"></i> Excel</a>
                </div>
            </form>

            @if ($missing !== [])
                <div class="alert alert-warning py-2">
                    <strong><i class="far fa-exclamation-triangle me-1"></i> Journées non clôturées :</strong>
                    <ul class="mb-0 small">
                        @foreach ($missing as $m)
                            <li>{{ $m['day']->format('d/m/Y') }} — {{ $m['user'] }} ({{ $fcfa($m['amount']) }} F encaissés)</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <table class="table align-middle">
                <thead><tr><th>Journée</th><th>Réceptionniste</th><th class="text-end">Attendu</th><th class="text-end">Remis</th><th class="text-end" data-card-title>Écart</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($closings as $c)
                        <tr>
                            <td data-order="{{ $c->business_date->format('Ymd') }}">{{ $c->business_date->format('d/m/Y') }}</td>
                            <td>{{ $c->user?->name }}</td>
                            <td class="text-end">{{ $fcfa($c->total_expected) }} F</td>
                            <td class="text-end">{{ $fcfa($c->total_declared) }} F</td>
                            <td class="text-end fw-semibold {{ (float) $c->total_difference < 0 ? 'text-danger' : ((float) $c->total_difference > 0 ? 'text-warning' : 'text-success') }}">{{ (float) $c->total_difference > 0 ? '+' : '' }}{{ $fcfa($c->total_difference) }} F</td>
                            <td><span class="badge bg-{{ $c->statusColor() }}">{{ $c->statusLabel() }}</span></td>
                            <td class="text-end"><a href="{{ route('admin.cash-closings.show', $c) }}" class="btn btn-sm btn-outline-primary">{{ $c->status === \App\Models\CashClosing::STATUS_SUBMITTED ? 'Vérifier' : 'Voir' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune clôture sur cette période.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $closings->links() }}
        </div>
    @endif
@endsection
