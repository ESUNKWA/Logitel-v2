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
    <div class="room-board">
        @foreach ($group['rooms'] as $room)
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
                            <a href="{{ route('admin.affectations.show', $room->currentStay) }}" class="small">Ouvrir (check-out)</a>
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

                @if ($room->status->value === 'disponible' && ! $room->isReserved() && $room->unguaranteedArrival)
                    <div class="room-card__notice mt-2" title="Réservation non soldée : elle ne bloque pas la chambre">
                        <i class="fas fa-exclamation-circle"></i>
                        Réservation non soldée aujourd'hui ({{ $room->unguaranteedArrival->check_in->format('H\hi') }})
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    </section>
    @endif
    @endforeach

    {{-- Room assignment modal, shared by every clickable room card (see public/js/admin.js) --}}
    <div class="modal fade" id="assignRoomModal" tabindex="-1" aria-hidden="true" data-reopen-room="{{ $reopenRoomId }}">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="">
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
                      <div class="row g-4">
                        <div class="col-lg-5">
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


                        @can('payments.create')
                            <h6 class="text-uppercase text-muted small fw-bold mt-4 mb-2">Encaissement <span class="text-lowercase fw-normal">(obligatoire : totalité du séjour)</span></h6>
                            <p class="text-muted small mb-2">Le client règle tout à l'affectation, en un ou plusieurs modes. En espèces, saisissez le montant reçu : la monnaie à rendre s'affiche.</p>
                            @include('admin.payments._lines', ['due' => null, 'required' => true, 'full' => true, 'cashReceivedRequired' => true])
                        @endcan
                        </div>

                        <div class="col-lg-7 border-start-lg">
                        <h6 class="text-uppercase text-muted small fw-bold mb-3">Séjour</h6>
                        @php($nightOpen = \App\Enums\PricingMode::Nuitee->canStartAt(now()))
                        <div
                            class="row g-3"
                            data-stay-form
                            data-night-from="{{ config('hotel.night.from') }}"
                            data-night-until="{{ config('hotel.night.until') }}"
                            data-stay-hours="{{ config('hotel.stay_min_hours') }}"
                            data-day-hours="{{ config('hotel.day_hours') }}"
                            data-night-open="{{ $nightOpen ? '1' : '0' }}"
                        >
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
                                <input type="text" inputmode="numeric" autocomplete="off" data-money class="form-control" id="assign-rate" name="unit_rate" value="{{ old('unit_rate') }}" data-unit-rate-input required>
                                <div class="form-text">Pré-rempli avec le tarif du type de chambre, modifiable (remise, prix négocié).</div>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-primary mb-0 py-3 fs-6" data-stay-preview>Choisissez un mode.</div>
                            </div>
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
