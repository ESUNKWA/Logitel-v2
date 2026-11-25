@extends('admin.layouts.admin')

@section('title', 'Prises de service')
@section('sweetalert-confirm', '1')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $weekdays = ['Dim.', 'Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.'];
        $receptionistSelect = function (string $label, ?int $selected) use ($receptionists) {
            return view('admin.duty._receptionist-select', ['receptionists' => $receptionists, 'label' => $label, 'selected' => $selected])->render();
        };
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>Prises de service</h1>
            <div class="dash-subtitle">Une réceptionniste de service à la fois, chacune ses ventes : chaque encaissement et chaque affectation revient à celle qui était de service à ce moment-là, même saisi par une collègue ou par vous. Plusieurs relèves par jour sont possibles. Vous pouvez corriger la réceptionniste d'une période, désigner celle d'une journée sans période, ou terminer un service resté ouvert.</div>
        </div>
        <div>
            @if ($current)
                <span class="duty-pill"><i class="far fa-user-clock"></i><span class="duty-pill__text"><span class="duty-pill__label">De service : </span>{{ $current->user?->name }} depuis {{ $current->started_at->format('d/m H\hi') }}</span></span>
            @else
                <span class="duty-pill duty-pill--none"><i class="far fa-user-clock"></i><span class="duty-pill__text">Personne de service</span></span>
            @endif
        </div>
    </div>

    @if ($receptionists->isEmpty())
        <div class="alert alert-info">Aucun compte réceptionniste actif : les ventes restent attribuées à la personne qui les saisit.</div>
    @endif

    <div class="admin-card">
        <table class="table align-middle duty-table">
            <thead>
                <tr>
                    <th>Journée</th>
                    <th>Réceptionniste</th>
                    <th>Période de service</th>
                    <th class="text-end">Encaissé pendant la période</th>
                    <th>Corriger</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($days as $row)
                    @php($day = $row['date'])
                    @php($shifts = $row['shifts'])
                    @forelse ($shifts as $i => $shift)
                        @php([$total] = $shift->salesSummary())
                        <tr>
                            <td>
                                @if ($i === 0)
                                    <strong>{{ $weekdays[$day->dayOfWeek] }} {{ $day->format('d/m/Y') }}</strong>
                                    @if ($day->equalTo($today))
                                        <span class="badge bg-success ms-1">Aujourd'hui</span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <i class="far fa-user-clock text-muted"></i> {{ $shift->user?->name }}
                                @if ($shift->assignedBy)
                                    <div class="small text-muted">désignée par {{ $shift->assignedBy->name }}</div>
                                @endif
                            </td>
                            <td class="small">
                                {{ $shift->started_at->format('d/m H\hi') }} →
                                @if ($shift->ended_at)
                                    {{ $shift->ended_at->format('d/m H\hi') }}
                                    @if ($shift->endedBy && $shift->ended_by !== $shift->user_id)
                                        <div class="text-muted">relève : {{ $shift->endedBy->name }}</div>
                                    @endif
                                @else
                                    <span class="badge bg-success">en cours</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $fcfa($total) }} F</td>
                            <td>
                                @if ($receptionists->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('admin.duty.reassign', $shift) }}" class="d-flex gap-2 duty-assign"
                                            data-confirm="Toutes les ventes de la période du {{ $shift->started_at->format('d/m/Y à H\hi') }} seront attribuées à la réceptionniste choisie. Continuer ?"
                                            data-confirm-button="Oui, attribuer">
                                            @csrf
                                            {!! $receptionistSelect('Réceptionniste de la période du '.$shift->started_at->format('d/m/Y H\hi'), $shift->user_id) !!}
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Valider</button>
                                        </form>
                                        @if ($shift->isOpen())
                                            <form method="POST" action="{{ route('admin.duty.close', $shift) }}"
                                                data-confirm="Le service de {{ $shift->user?->name }} sera terminé maintenant ; la prochaine réceptionniste le prendra à sa connexion."
                                                data-confirm-button="Oui, terminer">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Terminer ce service</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td>
                                <strong>{{ $weekdays[$day->dayOfWeek] }} {{ $day->format('d/m/Y') }}</strong>
                                @if ($day->equalTo($today))
                                    <span class="badge bg-success ms-1">Aujourd'hui</span>
                                @endif
                            </td>
                            <td><span class="text-muted">— personne</span></td>
                            <td class="small">—</td>
                            <td class="text-end">—</td>
                            <td>
                                @if ($receptionists->isNotEmpty())
                                    <form method="POST" action="{{ route('admin.duty.assign') }}" class="d-flex gap-2 duty-assign"
                                        data-confirm="Toutes les ventes de la journée du {{ $day->format('d/m/Y') }} seront attribuées à la réceptionniste choisie. Continuer ?"
                                        data-confirm-button="Oui, attribuer">
                                        @csrf
                                        <input type="hidden" name="business_date" value="{{ $day->toDateString() }}">
                                        {!! $receptionistSelect('Réceptionniste du '.$day->format('d/m/Y'), null) !!}
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Désigner</button>
                                    </form>
                                @else
                                    <span class="small text-muted">Aucun compte réceptionniste actif.</span>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
