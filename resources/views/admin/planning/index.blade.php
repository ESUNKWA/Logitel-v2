@extends('admin.layouts.admin')

@section('title', 'Planning')

@section('content')
    @php($query = fn (array $extra = []) => array_filter(array_merge(['vue' => $view, 'date' => $date->format('Y-m-d'), 'type' => $typeId, 'libres' => $freeOnly ? '1' : null], $extra), fn ($v) => $v !== null && $v !== ''))
    @php($cols = $columns->count())

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-calendar-week text-primary me-2"></i>Planning</h1>
            <div class="dash-subtitle">
                Du {{ $start->format('d/m/Y H\hi') }} au {{ $end->copy()->subMinute()->format('d/m/Y H\hi') }}
                <span class="text-muted">· journées commerciales</span>
            </div>
        </div>
    </div>

    <div class="admin-card plan-toolbar">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="btn-group" role="group" aria-label="Vue">
                @foreach (\App\Http\Controllers\Admin\PlanningController::VIEWS as $key => $label)
                    <a href="{{ route('admin.planning.index', $query(['vue' => $key])) }}" class="btn btn-sm {{ $view === $key ? 'btn-primary' : 'btn-outline-primary' }}" @if ($view === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </div>
            <div class="btn-group" role="group" aria-label="Période">
                <a href="{{ route('admin.planning.index', $query(['date' => $prev])) }}" class="btn btn-sm btn-outline-secondary" aria-label="Précédent"><i class="far fa-chevron-left"></i></a>
                <a href="{{ route('admin.planning.index', $query(['date' => $today->format('Y-m-d')])) }}" class="btn btn-sm btn-outline-secondary">Aujourd'hui</a>
                <a href="{{ route('admin.planning.index', $query(['date' => $next])) }}" class="btn btn-sm btn-outline-secondary" aria-label="Suivant"><i class="far fa-chevron-right"></i></a>
            </div>
            <form method="GET" action="{{ route('admin.planning.index') }}" class="d-flex flex-wrap gap-2 align-items-center ms-md-auto">
                <input type="hidden" name="vue" value="{{ $view }}">
                <input type="date" class="form-control form-control-sm plan-toolbar__date" name="date" value="{{ $date->format('Y-m-d') }}" aria-label="Date">
                <select class="form-select form-select-sm plan-toolbar__type" name="type" aria-label="Type de chambre">
                    <option value="">Tous les types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected($typeId === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
                <label class="form-check form-check-inline mb-0 small">
                    <input type="checkbox" class="form-check-input" name="libres" value="1" @checked($freeOnly)>
                    <span class="form-check-label">Libres seulement</span>
                </label>
                <button type="submit" class="btn btn-sm btn-primary"><i class="far fa-filter"></i> Afficher</button>
            </form>
        </div>
        <div class="plan-legend small mt-2" aria-label="Légende">
            <span><i class="plan-swatch plan-block--stay"></i> Affectation en cours</span>
            <span><i class="plan-swatch plan-block--done"></i> Terminée</span>
            <span><i class="plan-swatch plan-block--booking"></i> Réservation soldée</span>
            <span><i class="plan-swatch plan-block--booking-unpaid"></i> Réservation non soldée</span>
            <span><i class="plan-swatch plan-block--request"></i> Demande client</span>
            <span><i class="plan-swatch plan-swatch--buffer"></i> Ménage</span>
            @if ($canBook || $canAssign)
                <span class="text-muted">· Cliquez sur une case libre pour réserver ou affecter</span>
            @endif
        </div>
    </div>

    <div class="admin-card plan-card p-0">
        <div class="plan-scroll" data-planning
            data-start="{{ $start->getTimestamp() }}" data-end="{{ $end->getTimestamp() }}" data-now="{{ now()->getTimestamp() }}" data-view="{{ $view }}"
            data-offset="{{ now()->getOffset() }}" data-day-start="{{ \Illuminate\Support\Carbon::parse(\App\Support\BusinessDay::startTime())->secondsSinceMidnight() }}"
            data-book-url="{{ $canBook ? route('admin.reservations.create') : '' }}" data-assign-url="{{ $canAssign ? route('admin.rooms.board') : '' }}"
            style="--plan-cols: {{ $cols }}">
            <div class="plan-grid plan-grid--{{ $view }}">
                <div class="plan-head">
                    <div class="plan-room plan-room--head">Chambre</div>
                    <div class="plan-cols">
                        @foreach ($columns as $col)
                            <div class="plan-col {{ $col['today'] ? 'is-today' : '' }}">{{ $col['label'] }}@if ($col['sub'])<small>{{ $col['sub'] }}</small>@endif</div>
                        @endforeach
                    </div>
                </div>

                @php($currentType = null)
                @forelse ($rows as $row)
                    @php($room = $row['room'])
                    @if ($currentType !== $room->room_type_id)
                        @php($currentType = $room->room_type_id)
                        <div class="plan-type"><span>{{ $room->roomType?->name }}</span></div>
                    @endif
                    @php($hs = $room->status === \App\Enums\RoomStatus::HorsService)
                    <div class="plan-row {{ $hs ? 'is-out' : '' }}" style="--plan-lanes: {{ $row['lanes'] }}">
                        <div class="plan-room">
                            <strong>N°{{ $room->number }}</strong>
                            @if ($hs)
                                <span class="badge bg-secondary">Hors service</span>
                            @elseif (isset($availableNow[$room->id]))
                                <span class="plan-free" title="Libre maintenant"></span>
                            @endif
                        </div>
                        <div class="plan-track" data-plan-track data-room="{{ $room->id }}" @if ($hs) data-out @endif @if (isset($availableNow[$room->id])) data-free-now @endif
                            aria-label="Chambre N°{{ $room->number }}">
                            @if ($nowLeft !== null)
                                <span class="plan-now" style="left: {{ $nowLeft }}%" aria-hidden="true"></span>
                            @endif
                            @foreach ($row['blocks'] as $b)
                                @if ($b['bufferWidth'] > 0)
                                    <span class="plan-buffer" style="left: {{ $b['left'] + $b['width'] }}%; width: {{ $b['bufferWidth'] }}%; top: calc({{ $b['lane'] }} * var(--plan-lane))" aria-hidden="true"></span>
                                @endif
                                @php($tag = $b['url'] ? 'a' : 'span')
                                <{{ $tag }} @if ($b['url']) href="{{ $b['url'] }}" @else tabindex="0" @endif
                                    class="plan-block plan-block--{{ $b['kind'] }} mode--{{ $b['mode']?->value }} {{ $b['overdue'] ? 'is-overdue' : '' }} {{ $b['clippedStart'] ? 'is-clipped-start' : '' }} {{ $b['clippedEnd'] ? 'is-clipped-end' : '' }}"
                                    style="left: {{ $b['left'] }}%; width: {{ $b['width'] }}%; top: calc({{ $b['lane'] }} * var(--plan-lane))"
                                    data-tip="{{ $b['tip'] }}" aria-label="{{ $b['tip'] }}">
                                    @if ($b['mode'])<i class="fas {{ $b['mode']->icon() }}" aria-hidden="true"></i>@endif
                                    <span class="plan-block__label">{{ $b['label'] }}</span>
                                </{{ $tag }}>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="plan-empty text-muted">Aucune chambre à afficher avec ces filtres.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
