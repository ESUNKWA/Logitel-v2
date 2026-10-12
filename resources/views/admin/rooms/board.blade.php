@extends('admin.layouts.admin')

@section('title', 'Chambres')

@section('content')
    <div class="admin-page-header">
        <h1>Chambres</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.rooms.index') }}" class="btn btn-outline-primary">
                <i class="far fa-table"></i> Vue tableau
            </a>
            <a href="{{ route('admin.rooms.index', ['status' => 'occupee']) }}" class="btn btn-outline-danger">
                <i class="far fa-bed"></i> Chambres occupées
            </a>
            @can('rooms.manage')
                <a href="{{ route('admin.rooms.create') }}" class="btn btn-primary">
                    <i class="far fa-plus"></i> Nouvelle chambre
                </a>
            @endcan
        </div>
    </div>

    @php($canAssign = auth()->user()->can('affectations.manage'))
    {{-- After a failed assignment, reopen the modal on the same room with the input and errors (inline). --}}
    @php($reopenRoomId = $canAssign && $errors->any() && $rooms->contains(fn ($r) => (string) $r->id === (string) old('assign_room_id') && $r->isAvailableNow()) ? old('assign_room_id') : null)
    @if ($reopenRoomId)
        @section('inline-errors', '1')
    @endif

    {{-- Rate grid: every formula per room type, to inform guests (folded by default). --}}
    @php($nightOpen = \App\Enums\PricingMode::Nuitee->canStartAt(now()))
    @if ($rateGrid->isNotEmpty())
        <details class="rate-grid admin-card" data-rate-grid>
            <summary><i class="fas fa-tags me-2"></i>Grille tarifaire <span class="text-muted fw-normal">— tarifs par type de chambre (FCFA)</span></summary>
            <div class="table-responsive mt-3">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Type de chambre</th>
                            @foreach (\App\Enums\PricingMode::cases() as $mode)
                                <th class="text-end">{{ $mode->getLabel() }}@if ($mode === \App\Enums\PricingMode::Nuitee)<div class="small text-muted fw-normal">{{ str_replace(':', 'h', config('hotel.night.from')) }} → {{ str_replace(':', 'h', config('hotel.night.until')) }}</div>@endif</th>
                            @endforeach
                            <th class="text-end">Libres</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rateGrid as $row)
                            <tr>
                                <td><strong>{{ $row['type']->name }}</strong>@if ($row['type']->capacity_adults)<div class="small text-muted">{{ $row['type']->capacity_adults }} adulte(s){{ $row['type']->capacity_children ? ' + '.$row['type']->capacity_children.' enfant(s)' : '' }}</div>@endif</td>
                                @foreach (\App\Enums\PricingMode::cases() as $mode)
                                    <td class="text-end">{!! $mode->rateFor($row['type']) !== null ? '<strong>'.e(number_format((float) $mode->rateFor($row['type']), 0, ',', ' ')).'</strong>' : '<span class="text-muted">—</span>' !!}</td>
                                @endforeach
                                <td class="text-end"><span class="badge bg-{{ $row['free'] ? 'success' : 'secondary' }}">{{ $row['free'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    @if ($canAssign)
        <p class="text-muted mb-3" data-room-tab-only="disponible">Cliquez sur une chambre <strong>disponible</strong> pour l'affecter à un client : il s'installe maintenant (check-in immédiat).</p>
    @endif

    {{-- One tab per state (admin.js shows a single panel at a time; without JS every section shows). --}}
    <div class="room-sections-nav room-tabs" role="tablist" aria-label="États des chambres" data-room-tabs>
        @foreach ($groups as $state => $group)
            <button
                type="button"
                role="tab"
                id="tab-{{ $state }}"
                class="room-sections-nav__item {{ $group['rooms']->isEmpty() ? 'is-empty' : '' }}"
                aria-controls="etat-{{ $state }}"
                aria-selected="false"
                data-room-tab="{{ $state }}"
                @disabled($group['rooms']->isEmpty())
            >
                <span class="room-sections-nav__dot room-sections-nav__dot--{{ $group['color'] }}"></span>
                {{ $group['label'] }} <strong>{{ $group['rooms']->count() }}</strong>
            </button>
        @endforeach
    </div>

    @foreach ($groups as $state => $group)
    @if ($group['rooms']->isNotEmpty())
    <section class="room-section" id="etat-{{ $state }}" role="tabpanel" aria-labelledby="tab-{{ $state }}" data-room-panel="{{ $state }}">
        <h2 class="room-section__title">
            <span class="room-sections-nav__dot room-sections-nav__dot--{{ $group['color'] }}"></span>
            {{ $group['label'] }}
            <span class="room-section__count">{{ $group['rooms']->count() }}</span>
        </h2>
    {{-- Free rooms are grouped by room type (client request); the other states stay one grid. --}}
    @php($byType = $state === 'disponible' ? $group['rooms']->groupBy(fn ($r) => $r->roomType?->name ?? 'Sans type')->sortKeys(SORT_NATURAL | SORT_FLAG_CASE) : collect(['' => $group['rooms']]))
    @foreach ($byType as $typeName => $typeRooms)
    @if ($state === 'disponible')
        @php($lowest = collect(\App\Enums\PricingMode::cases())->map(fn ($m) => [$m, $m->rateFor($typeRooms->first()->roomType)])->filter(fn ($p) => $p[1] !== null)->sortBy(fn ($p) => (float) $p[1])->first())
        <h3 class="room-type-heading">
            <span class="room-type-heading__name">{{ $typeName }}</span>
            <span class="room-type-heading__count">{{ $typeRooms->count() }} libre{{ $typeRooms->count() > 1 ? 's' : '' }}</span>
            @if ($lowest)
                <span class="room-type-heading__rate">dès {{ number_format((float) $lowest[1], 0, ',', ' ') }} F {{ $lowest[0]->rateUnit() }}</span>
            @endif
        </h3>
    @endif
    <div class="room-board">
        @foreach ($typeRooms as $room)
            @php($assignable = $room->isAvailableNow() && $canAssign)
            <div
                class="room-card room-card--{{ in_array($room->displayState(), ['reservee', 'demande'], true) ? $room->displayState() : $room->status->value }} {{ $assignable ? 'is-clickable' : '' }}"
                @if ($assignable)
                    data-assign-room
                    data-room-id="{{ $room->id }}"
                    data-assign-url="{{ route('admin.rooms.assign', $room) }}"
                    data-room-number="{{ $room->number }}"
                    data-room-type="{{ $room->roomType?->name }}"
                    data-hourly-rate="{{ $room->roomType?->hourly_rate }}"
                    data-day-rate="{{ $room->roomType?->day_rate }}"
                    data-night-rate="{{ $room->roomType?->night_rate }}"
                    data-stay-rate="{{ $room->roomType?->stay_rate }}"
                @endif
            >
                <div class="room-card__number">N°{{ $room->number }}</div>
                <div class="room-card__type">{{ $room->roomType?->name }}</div>
                @if ($room->isReserved())
                    <span class="badge bg-info">Réservée</span>
                @elseif ($room->isRequested())
                    <span class="badge bg-primary">Demande client</span>
                @else
                    <span class="badge bg-{{ $room->status->getColor() }}">{{ $room->status->getLabel() }}</span>
                @endif

                @if ($room->isReserved())
                    @can('reservations.view')
                        <div class="mt-2 small">
                            {{ $room->pendingArrival->guest?->fullName() }}<br>
                            <span class="text-muted">arrivée {{ $room->pendingArrival->check_in->format('d/m H\hi') }}</span>
                        </div>
                    @endcan
                    @can('reservations.manage')
                        <div class="mt-1">
                            <a href="{{ route('admin.reservations.edit', $room->pendingArrival) }}" class="small">Ouvrir (check-in)</a>
                        </div>
                    @endcan
                @elseif ($room->isRequested())
                    {{-- Held by a guest's request made from their phone: the réception validates it (and cashes) or refuses it. --}}
                    @can('affectations.view')
                        <div class="mt-2 small">
                            {{ $room->pendingRequest->fullName() }}<br>
                            <span class="text-muted">{{ $room->pendingRequest->pricing_mode->getLabel() }} · retenue jusqu'à {{ $room->pendingRequest->expires_at->format('H\hi') }}</span>
                        </div>
                        <div class="mt-1">
                            <a href="{{ route('admin.stay-requests.show', $room->pendingRequest) }}" class="small">Traiter la demande</a>
                        </div>
                    @endcan
                @elseif ($room->currentStay)
                    {{-- Occupied: when the guest came in and when they must leave (no guest name, by request) --}}
                    @can('affectations.view')
                        <div class="room-card__stay">
                            <div class="room-card__time">
                                <i class="fas fa-sign-in-alt"></i> Entrée
                                <strong>{{ $room->currentStay->check_in->format('d/m H\hi') }}</strong>
                            </div>
                            <div class="room-card__time {{ $room->currentStay->check_out->isPast() ? 'is-overdue' : '' }}">
                                <i class="fas fa-sign-out-alt"></i> Sortie
                                <strong>{{ $room->currentStay->check_out->format('d/m H\hi') }}</strong>
                                @if ($room->currentStay->check_out->isPast())
                                    <span class="badge bg-danger">Dépassée</span>
                                @endif
                            </div>
                        </div>
                    @endcan
                    @can('affectations.view')
                        <div class="mt-2">
                            <a href="{{ route('admin.affectations.show', $room->currentStay) }}" class="small">Ouvrir (sortie)</a>
                        </div>
                    @endcan
                @elseif ($room->status->value !== 'disponible')
                    @if ($room->status->value === 'nettoyage')
                        @can('rooms.edit')
                            <form method="POST" action="{{ route('admin.rooms.ready', $room) }}" class="mt-2 room-card__action">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-check"></i> Chambre prête</button>
                            </form>
                        @endcan
                    @endif
                    @can('affectations.view')
                        <div class="mt-2">
                            <a href="{{ route('admin.affectations.index', ['onglet' => 'terminees', 'room' => $room->number]) }}" class="small">Dernier séjour</a>
                        </div>
                    @endcan
                @elseif ($room->nextArrival)
                    <div class="mt-2 small text-muted">Réservée à partir du {{ $room->nextArrival->check_in->format('d/m/Y') }}</div>
                @endif

                @if ($room->isAvailableNow())
                    {{-- Free room: its formulas and prices, to inform the guest before assigning. --}}
                    <ul class="room-card__rates">
                        @foreach (\App\Enums\PricingMode::cases() as $mode)
                            @if ($mode->rateFor($room->roomType) !== null)
                                <li>
                                    <span>{{ $mode->getLabel() }}@if ($mode === \App\Enums\PricingMode::Nuitee && ! $nightOpen) <small class="text-muted">dès {{ str_replace(':', 'h', config('hotel.night.from')) }}</small>@endif</span>
                                    <strong>{{ number_format((float) $mode->rateFor($room->roomType), 0, ',', ' ') }}</strong>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif

                @if ($room->status->value === 'disponible' && ! $room->isReserved() && $room->unguaranteedArrival)
                    <div class="room-card__notice mt-2" title="Réservation non soldée : elle ne bloque pas la chambre">
                        <i class="fas fa-exclamation-circle"></i>
                        Réservation non soldée aujourd'hui ({{ $room->unguaranteedArrival->check_in->format('H\hi') }})
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @endforeach
    </section>
    @endif
    @endforeach

    {{-- Room assignment modal, shared by every clickable room card (see public/js/admin.js) --}}
    <div class="modal fade" id="assignRoomModal" tabindex="-1" aria-hidden="true" data-reopen-room="{{ $reopenRoomId }}">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <form method="POST" action="" class="modal-form">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" data-room-label>Affecter une chambre</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body p-4">
                      <input type="hidden" name="assign_room_id" value="{{ old('assign_room_id') }}" data-assign-room-id>
                      @if ($reopenRoomId && collect($errors->messages())->except(['new_first_name', 'new_last_name', 'new_phone'])->isNotEmpty())
                          <div class="alert alert-danger" data-modal-errors>
                              <ul class="mb-0">
                                  @foreach (collect($errors->messages())->except(['new_first_name', 'new_last_name', 'new_phone'])->flatten()->unique() as $message)
                                      <li>{{ $message }}</li>
                                  @endforeach
                              </ul>
                          </div>
                      @endif
                      {{-- Left: who and what (client, stay). Right: « Règlement », in the order it's typed —
                           discount, total to pay, then the encaissement (method, amount, cash received →
                           change). data-stay-form spans both columns: admin.js reads the stay fields and
                           writes the total in [data-stay-preview]. --}}
                      @php($nightOpen = \App\Enums\PricingMode::Nuitee->canStartAt(now()))
                      <div class="row g-4"
                          data-stay-form
                          data-night-from="{{ config('hotel.night.from') }}"
                          data-night-until="{{ config('hotel.night.until') }}"
                          data-stay-hours="{{ config('hotel.stay_min_hours') }}"
                          data-day-hours="{{ config('hotel.day_hours') }}"
                          data-night-open="{{ $nightOpen ? '1' : '0' }}"
                      >
                        <div class="col-lg-6">
                        <h6 class="text-uppercase text-muted small fw-bold mb-3">Client <span class="text-lowercase fw-normal">(facultatif)</span></h6>
                        <div class="mb-3 position-relative">
                            <label class="form-label">Rechercher un client existant</label>
                            <input
                                type="text"
                                class="form-control"
                                autocomplete="off"
                                placeholder="Nom ou téléphone…"
                                name="guest_label"
                                value="{{ old('guest_label') }}"
                                data-guest-search
                                data-guest-search-url="{{ route('admin.guests.search') }}"
                                data-guest-id-target="#assign-guest-id"
                            >
                            <div class="list-group position-absolute w-100" style="z-index: 1080;" data-guest-search-results></div>
                            <input type="hidden" id="assign-guest-id" name="guest_id" value="{{ old('guest_id') }}">
                        </div>

                        <p class="text-muted small mb-2">Ou saisir un nouveau client (tout est facultatif ; sans nom, la fiche est créée en « Client de passage », à compléter plus tard) :</p>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="text" class="form-control @error('new_first_name') is-invalid @enderror" name="new_first_name" value="{{ old('new_first_name') }}" placeholder="Prénom">
                                @error('new_first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <input type="text" class="form-control @error('new_last_name') is-invalid @enderror" name="new_last_name" value="{{ old('new_last_name') }}" placeholder="Nom">
                                @error('new_last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <input type="text" class="form-control @error('new_phone') is-invalid @enderror" name="new_phone" value="{{ old('new_phone') }}" placeholder="Téléphone">
                                @error('new_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <h6 class="text-uppercase text-muted small fw-bold mt-4 mb-3">Séjour</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label d-block">Mode</label>
                                <div class="btn-group w-100" role="group">
                                    @foreach (\App\Enums\PricingMode::cases() as $mode)
                                        <input type="radio" class="btn-check" name="pricing_mode" id="mode-{{ $mode->value }}" value="{{ $mode->value }}" data-mode-option @checked(old('pricing_mode') === $mode->value)>
                                        <label class="btn btn-outline-primary" for="mode-{{ $mode->value }}">{{ $mode->getLabel() }}</label>
                                    @endforeach
                                </div>
                                <div class="form-text" data-mode-hint>
                                    Nuitée : de {{ str_replace(':', 'h', config('hotel.night.from')) }} à {{ str_replace(':', 'h', config('hotel.night.until')) }}@unless ($nightOpen), disponible à partir de {{ str_replace(':', 'h', config('hotel.night.from')) }}@endunless. Les modes sans tarif pour ce type de chambre sont désactivés.
                                </div>
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="assign-duration" data-duration-label>Durée</label>
                                <input type="number" min="1" max="365" step="1" class="form-control" id="assign-duration" name="duration" value="{{ old('duration', 1) }}" data-duration-input required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Adultes</label>
                                <input type="number" min="1" value="{{ old('adults', 1) }}" class="form-control" name="adults" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Enfants</label>
                                <input type="number" min="0" value="{{ old('children', 0) }}" class="form-control" name="children" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="assign-rate">Tarif <span data-rate-unit></span> (FCFA)</label>
                                <input type="text" class="form-control rate-locked" id="assign-rate" value="" data-money data-unit-rate-input readonly tabindex="-1">
                                <div class="form-text">Tarif du type de chambre (non modifiable). Pour une réduction, utilisez la remise.</div>
                            </div>
                        </div>
                        </div>

                        <div class="col-lg-6 border-start-lg">
                          <div class="assign-pay">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3">Règlement</h6>
                            <div class="assign-pay__total" data-stay-preview>Choisissez un mode.</div>

                            {{-- Payment lines, then — right under « Ajouter un mode » — the discount and the cash received. --}}
                            @can('payments.create')
                                <div class="assign-pay__label">Encaissement <span class="text-muted fw-normal">— totalité du séjour, en un ou plusieurs modes</span></div>
                                @include('admin.payments._lines', ['due' => null, 'required' => true, 'full' => true, 'cashReceivedRequired' => true,
                                    'sharedReceived' => true, 'idPrefix' => 'assign',
                                    'afterActions' => view('admin.partials._discount-fields', ['idPrefix' => 'assign'])->render()])
                            @else
                                @include('admin.partials._discount-fields', ['idPrefix' => 'assign'])
                            @endcan
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Affecter la chambre</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
