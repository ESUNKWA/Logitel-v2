@csrf
@if ($bookingRequest ?? null)
    <input type="hidden" name="booking_request_id" value="{{ $bookingRequest->id }}">
@endif

{{-- A réservation in any of the four modes (public/js/admin.js, [data-booking-form]). The arrival
     is typed as a date + a time (client request, 2026-11-10: two separate, mandatory fields, empty
     on a new booking); admin.js keeps the hidden check_in (= date + time) for the room list and
     the preview, and ReservationRequest rebuilds it from the two fields. --}}
@php
    $mode = \App\Enums\PricingMode::tryFrom((string) old('pricing_mode', $reservation->pricing_mode?->value)) ?? \App\Enums\PricingMode::Nuitee;
    $isNight = $mode === \App\Enums\PricingMode::Nuitee;
    $arrivalDate = (string) old('arrival_date', $reservation->check_in?->toDateString() ?? '');
    $arrivalTime = (string) old('arrival_time', $reservation->check_in?->format('H:i') ?? '');
    $checkIn = $arrivalDate !== '' && $arrivalTime !== '' ? $arrivalDate.'T'.$arrivalTime : '';
@endphp

<div
    class="row g-3"
    data-booking-form
    data-night-from="{{ config('hotel.night.from') }}"
    data-night-until="{{ config('hotel.night.until') }}"
    data-day-hours="{{ config('hotel.day_hours') }}"
    data-stay-hours="{{ config('hotel.stay_min_hours') }}"
>
    {{-- Client: no search of existing clients any more (client request, 2026-11-10) — a new client
         is entered here (only the phone is required), natural person or company. A booking that
         already has one (editing, or an online request matched by phone) keeps it unless « Remplacer
         par un nouveau client » is ticked (admin.js then stops sending guest_id). --}}
    @php
        $currentGuest = filled(old('guest_id')) ? \App\Models\Guest::find(old('guest_id')) : $reservation->guest;
        $replacing = $currentGuest && old('replace_client');
        $newType = old('new_client_type', \App\Models\Guest::TYPE_PERSON);
    @endphp
    <div class="col-12">
        <div class="booking-client" data-client-form>
            <label class="form-label d-block">Client</label>
            @if ($currentGuest)
                <div class="booking-current-client mb-2" data-current-client>
                    <div><i class="far fa-user me-1"></i> <strong>{{ $currentGuest->fullName() }}</strong>@if ($currentGuest->phone) — {{ \App\Support\PhoneFormat::display($currentGuest->phone) }}@endif</div>
                    <input type="hidden" name="guest_id" value="{{ $currentGuest->id }}" data-current-client-id @disabled($replacing)>
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" name="replace_client" value="1" id="replace-client" data-replace-client @checked($replacing)>
                        <label class="form-check-label" for="replace-client">Remplacer par un nouveau client</label>
                    </div>
                </div>
            @endif

            <div data-new-client @if ($currentGuest && ! $replacing) hidden @endif>
                <p class="text-muted small mb-2">Nouveau client <span class="fw-semibold">(seul le téléphone est obligatoire)</span> :</p>
                <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Type de client">
                    <input type="radio" class="btn-check" name="new_client_type" id="new-type-physique" value="physique" data-client-type @checked($newType !== 'morale')>
                    <label class="btn btn-outline-primary" for="new-type-physique"><i class="far fa-user me-1"></i> Particulier</label>
                    <input type="radio" class="btn-check" name="new_client_type" id="new-type-morale" value="morale" data-client-type @checked($newType === 'morale')>
                    <label class="btn btn-outline-primary" for="new-type-morale"><i class="far fa-building me-1"></i> Entreprise</label>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="tel" class="form-control @error('new_phone') is-invalid @enderror" name="new_phone" value="{{ old('new_phone', $prefillClient['phone'] ?? '') }}" placeholder="Téléphone *" aria-label="Téléphone du nouveau client" data-new-client-phone @if (! $currentGuest || $replacing) required @endif>
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
    </div>
    {{-- The room type comes first, then only its modes (client request, 2026-10-19: modes the
         type doesn't offer are hidden, not greyed); the room list is limited to that type. --}}
    @php
        $roomTypes = $roomTypes ?? collect();
        $selectedTypeId = (string) old('room_type_id', $reservation->room?->room_type_id ?? ($bookingRequest ?? null)?->room_type_id ?? ($roomTypes->count() === 1 ? $roomTypes->first()->id : ''));
        $selectedType = $roomTypes->first(fn ($type) => (string) $type->id === $selectedTypeId);
        $offers = fn ($case) => ! $selectedType || $case->rateFor($selectedType) !== null;
        $perkMenus = \App\Models\Perk::menusFor($roomTypes); // « Inclus : … » under the modes
    @endphp
    {{-- Type and room on the same row, on computers and phones (client request, 2026-11-11). --}}
    <div class="col-6">
        <label class="form-label" for="booking_room_type">Type de chambre</label>
        <select class="form-select @error('room_type_id') is-invalid @enderror" id="booking_room_type" name="room_type_id" data-booking-type required>
            <option value="">Choisissez un type de chambre…</option>
            @foreach ($roomTypes as $type)
                <option value="{{ $type->id }}" @selected((string) $type->id === $selectedTypeId)@foreach (\App\Enums\PricingMode::cases() as $case) data-{{ $case->value }}="{{ $case->rateFor($type) !== null ? '1' : '' }}"@endforeach data-perks="{{ json_encode((object) ($perkMenus[$type->id] ?? [])) }}">{{ $type->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6">
        <label class="form-label" for="room_id">Chambre</label>
        <select
            class="form-select"
            id="room_id"
            name="room_id"
            data-room-select
            data-availability-url="{{ route('admin.rooms.available') }}"
            data-exclude-reservation-id="{{ $reservation->id }}"
            @if ($bookingRequest ?? null) data-refresh-on-load data-preferred-type="{{ $bookingRequest->room_type_id }}" @endif
            @if (! ($currentRoomOption ?? null) && is_scalar(old('room_id'))) data-old-room="{{ old('room_id') }}" @endif
            required
        >
            @if ($currentRoomOption ?? null)
                <option value="{{ $currentRoomOption['id'] }}" data-rate="{{ $currentRoomOption['rate'] }}" selected>{{ $currentRoomOption['label'] }}</option>
            @else
                <option value="">Choisissez le type de chambre et l'arrivée</option>
            @endif
        </select>
        <div class="form-text" data-room-hint hidden></div>
    </div>

    <div class="col-12">
        <label class="form-label d-block">Mode</label>
        <div class="btn-group w-100 booking-modes" role="group">
            @foreach (\App\Enums\PricingMode::cases() as $case)
                <input type="radio" class="btn-check" name="pricing_mode" id="booking-mode-{{ $case->value }}" value="{{ $case->value }}" data-booking-mode data-availability-trigger @checked($mode === $case)@disabled(! $offers($case))>
                <label class="btn mode-btn mode--{{ $case->value }}" for="booking-mode-{{ $case->value }}"@unless ($offers($case)) hidden @endunless><i class="fas {{ $case->icon() }}" aria-hidden="true"></i> {{ $case->getLabel() }}</label>
            @endforeach
        </div>
        <div class="form-text" data-booking-type-hint{{ $selectedType ? ' hidden' : '' }}>Choisissez d'abord le type de chambre : seuls ses modes s'affichent.</div>
        <div class="perks-hint mt-2" data-perks-hint hidden></div>
    </div>

    <div class="col-6 col-md-3">
        <label class="form-label" for="arrival_date">Date d'arrivée</label>
        <input type="date" class="form-control @error('arrival_date') is-invalid @enderror" id="arrival_date" name="arrival_date" value="{{ $arrivalDate }}" data-arrival-date data-availability-trigger required>
        @error('arrival_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label" for="arrival_time">Heure d'arrivée</label>
        <input type="time" class="form-control @error('arrival_time') is-invalid @enderror" id="arrival_time" name="arrival_time" value="{{ $arrivalTime }}" data-arrival-time data-availability-trigger required>
        @error('arrival_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text" data-night-hint @unless ($isNight) hidden @endunless>Nuitée : de {{ str_replace(':', 'h', config('hotel.night.from')) }} à {{ str_replace(':', 'h', config('hotel.night.until')) }} le lendemain.</div>
        <input type="hidden" name="check_in" value="{{ $checkIn }}" data-check-in-input>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="duration" data-booking-duration-label>Nombre de {{ $mode->unitPlural() }}</label>
        <input type="number" min="1" max="365" class="form-control" id="duration" name="duration" value="{{ old('duration', $reservation->duration ?? 1) }}" data-booking-duration data-availability-trigger required>
    </div>

    {{-- Statut sits with the rate and occupants, so type and room share their own row. --}}
    <div class="col-md-3">
        <label class="form-label" for="status">Statut</label>
        <select class="form-select" id="status" name="status" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $reservation->status?->value ?? 'confirmee') === $status->value)>{{ $status->getLabel() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="unit_rate">Tarif <span data-booking-rate-unit>{{ $mode->rateUnit() }}</span> (FCFA)</label>
        <input type="text" class="form-control rate-locked" id="unit_rate" value="{{ $reservation->unit_rate !== null ? number_format((float) $reservation->unit_rate, 0, ',', ' ') : '' }}" data-money data-rate-target data-booking-rate readonly tabindex="-1">
        <div class="form-text">Tarif du type de chambre (non modifiable).</div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="adults">Adultes</label>
        <input type="number" min="1" class="form-control" id="adults" name="adults" value="{{ old('adults', $reservation->adults ?? 2) }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="children">Enfants</label>
        <input type="number" min="0" class="form-control" id="children" name="children" value="{{ old('children', $reservation->children ?? 0) }}" required>
    </div>

    <div class="col-12">
        <label class="form-label" for="notes">Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="2">{{ old('notes', $reservation->notes) }}</textarea>
    </div>

    {{-- « Règlement », in the order it's typed: discount, total, then the optional deposit. --}}
    <div class="col-12">
        <div class="assign-pay">
            <h6 class="text-uppercase text-muted small fw-bold mb-3">Règlement</h6>
            <div class="assign-pay__total" data-booking-preview>—</div>
            @php
                $discountFields = view('admin.partials._discount-fields', ['file' => $reservation->exists ? $reservation : null, 'idPrefix' => 'booking'])->render();
                $withDeposit = ! $reservation->exists && auth()->user()->can('payments.create');
            @endphp
            @if ($withDeposit)
                {{-- Deposit lines, then — right under « Ajouter un mode » — the discount and the cash received. --}}
                <div class="assign-pay__label">Acompte <span class="text-muted fw-normal">— facultatif : tout, une partie ou rien pour l'instant</span></div>
                @include('admin.payments._lines', ['due' => null, 'required' => false, 'prefill' => true, 'cashReceivedRequired' => true,
                    'sharedReceived' => true, 'idPrefix' => 'booking', 'afterActions' => $discountFields])
            @else
                {!! $discountFields !!}
            @endif
        </div>
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="{{ route('admin.reservations.index') }}" class="btn btn-outline-secondary">Annuler</a>
</div>
