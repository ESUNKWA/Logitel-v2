@csrf
@if ($bookingRequest ?? null)
    <input type="hidden" name="booking_request_id" value="{{ $bookingRequest->id }}">
@endif

{{-- A réservation in any of the four modes (public/js/admin.js, [data-booking-form]): the arrival
     is a date for a nuitée (night of …, from 22:00) and a date + time otherwise. --}}
@php
    $mode = \App\Enums\PricingMode::tryFrom((string) old('pricing_mode', $reservation->pricing_mode?->value)) ?? \App\Enums\PricingMode::Nuitee;
    $isNight = $mode === \App\Enums\PricingMode::Nuitee;
    $checkIn = old('check_in', $reservation->check_in ? ($isNight ? $reservation->check_in->toDateString() : $reservation->check_in->format('Y-m-d\TH:i')) : ($isNight ? today()->addDay()->toDateString() : now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i')));
@endphp

<div
    class="row g-3"
    data-booking-form
    data-night-from="{{ config('hotel.night.from') }}"
    data-night-until="{{ config('hotel.night.until') }}"
    data-day-hours="{{ config('hotel.day_hours') }}"
    data-stay-hours="{{ config('hotel.stay_min_hours') }}"
>
    {{-- Client: search an existing one, or enter a new one (only the phone is required),
         natural person or company. --}}
    @php
        $currentGuest = $reservation->guest;
        $guestLabel = old('guest_label', $currentGuest ? $currentGuest->fullName().($currentGuest->phone ? ' — '.$currentGuest->phone : '') : '');
        $newType = old('new_client_type', \App\Models\Guest::TYPE_PERSON);
    @endphp
    <div class="col-12">
        <div class="booking-client" data-client-form>
            <label class="form-label" for="booking-guest-search">Client existant</label>
            <div class="position-relative">
                <input type="text" class="form-control" id="booking-guest-search" name="guest_label" autocomplete="off"
                    placeholder="Rechercher par nom, entreprise ou téléphone…" value="{{ $guestLabel }}"
                    data-guest-search data-guest-search-url="{{ route('admin.guests.search') }}" data-guest-id-target="#booking-guest-id">
                <div class="list-group position-absolute w-100" style="z-index: 1080;" data-guest-search-results></div>
                <input type="hidden" id="booking-guest-id" name="guest_id" value="{{ old('guest_id', $reservation->guest_id) }}">
            </div>

            <p class="text-muted small mt-3 mb-2">Ou nouveau client <span class="fw-semibold">(seul le téléphone est obligatoire)</span> :</p>
            <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Type de client">
                <input type="radio" class="btn-check" name="new_client_type" id="new-type-physique" value="physique" data-client-type @checked($newType !== 'morale')>
                <label class="btn btn-outline-primary" for="new-type-physique"><i class="far fa-user me-1"></i> Particulier</label>
                <input type="radio" class="btn-check" name="new_client_type" id="new-type-morale" value="morale" data-client-type @checked($newType === 'morale')>
                <label class="btn btn-outline-primary" for="new-type-morale"><i class="far fa-building me-1"></i> Entreprise</label>
            </div>
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="tel" class="form-control @error('new_phone') is-invalid @enderror" name="new_phone" value="{{ old('new_phone', $prefillClient['phone'] ?? '') }}" placeholder="Téléphone *" aria-label="Téléphone du nouveau client">
                    @error('new_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4" data-client-only="morale">
                    <input type="text" class="form-control" name="new_company_name" value="{{ old('new_company_name') }}" placeholder="Raison sociale" aria-label="Raison sociale">
                </div>
                <div class="col-md-4" data-client-only="morale">
                    <input type="text" class="form-control" name="new_tax_id" value="{{ old('new_tax_id') }}" placeholder="N° RCCM / NCC" aria-label="N° RCCM / NCC">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" name="new_first_name" value="{{ old('new_first_name', $prefillClient['first_name'] ?? '') }}" placeholder="Prénom" aria-label="Prénom">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" name="new_last_name" value="{{ old('new_last_name', $prefillClient['last_name'] ?? '') }}" placeholder="Nom" aria-label="Nom">
                </div>
            </div>
            <div class="form-text" data-client-only="morale">Pour une entreprise, prénom et nom sont ceux de la personne à contacter.</div>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="status">Statut</label>
        <select class="form-select" id="status" name="status" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $reservation->status?->value ?? 'confirmee') === $status->value)>{{ $status->getLabel() }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12">
        <label class="form-label d-block">Mode</label>
        <div class="btn-group w-100 booking-modes" role="group">
            @foreach (\App\Enums\PricingMode::cases() as $case)
                <input type="radio" class="btn-check" name="pricing_mode" id="booking-mode-{{ $case->value }}" value="{{ $case->value }}" data-booking-mode data-availability-trigger @checked($mode === $case)>
                <label class="btn btn-outline-primary" for="booking-mode-{{ $case->value }}">{{ $case->getLabel() }}</label>
            @endforeach
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label" for="check_in" data-check-in-label>{{ $isNight ? 'Nuit du' : 'Arrivée le' }}</label>
        <input type="{{ $isNight ? 'date' : 'datetime-local' }}" class="form-control" id="check_in" name="check_in" value="{{ $checkIn }}" data-check-in-input data-availability-trigger required>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="duration" data-booking-duration-label>Nombre de {{ $mode->unitPlural() }}</label>
        <input type="number" min="1" max="365" class="form-control" id="duration" name="duration" value="{{ old('duration', $reservation->duration ?? 1) }}" data-booking-duration data-availability-trigger required>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="room_id">Chambre</label>
        <select
            class="form-select"
            id="room_id"
            name="room_id"
            data-room-select
            data-availability-url="{{ route('admin.rooms.available') }}"
            data-exclude-reservation-id="{{ $reservation->id }}"
            @if ($bookingRequest ?? null) data-refresh-on-load data-preferred-type="{{ $bookingRequest->room_type_id }}" @endif
            required
        >
            @if ($currentRoomOption ?? null)
                <option value="{{ $currentRoomOption['id'] }}" data-rate="{{ $currentRoomOption['rate'] }}" selected>{{ $currentRoomOption['label'] }}</option>
            @else
                <option value="">Renseignez d'abord l'arrivée</option>
            @endif
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label" for="unit_rate">Tarif <span data-booking-rate-unit>{{ $mode->rateUnit() }}</span> (FCFA)</label>
        <input type="text" class="form-control rate-locked" id="unit_rate" value="{{ $reservation->unit_rate !== null ? number_format((float) $reservation->unit_rate, 0, ',', ' ') : '' }}" data-money data-rate-target data-booking-rate readonly tabindex="-1">
        <div class="form-text">Tarif du type de chambre (non modifiable).</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="adults">Adultes</label>
        <input type="number" min="1" class="form-control" id="adults" name="adults" value="{{ old('adults', $reservation->adults ?? 1) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="children">Enfants</label>
        <input type="number" min="0" class="form-control" id="children" name="children" value="{{ old('children', $reservation->children ?? 0) }}" required>
    </div>

    <div class="col-12">
        @include('admin.partials._discount-fields', ['file' => $reservation->exists ? $reservation : null, 'idPrefix' => 'booking'])
    </div>

    <div class="col-12">
        <div class="alert alert-primary mb-0 py-2" data-booking-preview>—</div>
    </div>

    <div class="col-12">
        <label class="form-label" for="notes">Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="2">{{ old('notes', $reservation->notes) }}</textarea>
    </div>

    @if (! $reservation->exists)
        @can('payments.create')
            <div class="col-12">
                <h6 class="text-uppercase text-muted small fw-bold mt-2 mb-1">Acompte <span class="text-lowercase fw-normal">(facultatif)</span></h6>
                <p class="text-muted small mb-2">Le client peut tout payer, payer une partie, ou rien pour l'instant (espèces, Orange Money, Wave, ou mixte). En espèces, saisissez le montant reçu : la monnaie à rendre s'affiche.</p>
                @include('admin.payments._lines', ['due' => null, 'required' => false, 'cashReceivedRequired' => true])
            </div>
        @endcan
    @endif
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="{{ route('admin.reservations.index') }}" class="btn btn-outline-secondary">Annuler</a>
</div>
