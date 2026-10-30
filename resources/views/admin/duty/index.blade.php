@extends('admin.layouts.admin')

@section('title', 'Prises de service')
@section('sweetalert-confirm', '1')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $weekdays = ['Dim.', 'Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.'];
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>Prises de service</h1>
            <div class="dash-subtitle">Une réceptionniste par journée commerciale : toutes les ventes de la journée (encaissements et affectations) lui sont attribuées, même saisies par une collègue ou par vous. Vous pouvez désigner ou corriger la réceptionniste d'un jour.</div>
        </div>
    </div>

    <div class="admin-card">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Journée</th>
                    <th>Réceptionniste</th>
                    <th>Prise de service</th>
                    <th class="text-end">Encaissé ce jour</th>
                    <th>Désigner / corriger</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($days as $day)
                    @php($duty = $duties->get($day->toDateString()))
                    @php([$total, $count] = $duty ? $duty->salesSummary() : [null, null])
                    <tr>
                        <td data-order="{{ $day->toDateString() }}">
                            <strong>{{ $weekdays[$day->dayOfWeek] }} {{ $day->format('d/m/Y') }}</strong>
                            @if ($day->equalTo($today))
                                <span class="badge bg-success ms-1">Aujourd'hui</span>
                            @endif
                        </td>
                        <td>
                            @if ($duty)
                                <i class="far fa-user-clock text-muted"></i> {{ $duty->user?->name }}
                            @else
                                <span class="text-muted">— personne</span>
                            @endif
                        </td>
                        <td class="small">
                            @if ($duty)
                                {{ $duty->taken_at->format('d/m H\hi') }}
                                @if ($duty->assignedBy)
                                    <div class="text-muted">désignée par {{ $duty->assignedBy->name }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end">{{ $duty ? $fcfa($total).' F' : '—' }}</td>
                        <td>
                            @if ($receptionists->isNotEmpty())
                                <form method="POST" action="{{ route('admin.duty.assign') }}" class="d-flex gap-2 duty-assign"
                                    data-confirm="Toutes les ventes de la journée du {{ $day->format('d/m/Y') }} seront attribuées à la réceptionniste choisie. Continuer ?"
                                    data-confirm-button="Oui, attribuer">
                                    @csrf
                                    <input type="hidden" name="business_date" value="{{ $day->toDateString() }}">
                                    <select name="user_id" class="form-select form-select-sm" aria-label="Réceptionniste du {{ $day->format('d/m/Y') }}" required>
                                        <option value="">Choisir…</option>
                                        @foreach ($receptionists as $receptionist)
                                            <option value="{{ $receptionist->id }}" @selected($duty?->user_id === $receptionist->id)>{{ $receptionist->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Valider</button>
                                </form>
                            @else
                                <span class="small text-muted">Aucun compte réceptionniste actif.</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
