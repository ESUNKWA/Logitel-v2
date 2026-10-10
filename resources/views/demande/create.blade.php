@extends('demande.layout')

@section('title', 'Chambre N°'.$room->number)
@section('subtitle', 'Votre demande')

@section('content')
    <a href="{{ route('stay-request.index') }}" class="dm-back"><i class="far fa-arrow-left"></i> Autres chambres</a>

    <div class="dm-summary">
        <div class="dm-summary__photo">
            @if ($room->images->first())
                <img src="{{ $room->images->first()->url() }}" alt="{{ $room->displayName() }}">
            @else
                <i class="far fa-bed"></i>
            @endif
        </div>
        <div>
            <h1>{{ $room->displayName() }}</h1>
            <div class="dm-room__meta">Chambre N°{{ $room->number }}@if ($room->displayName() !== $room->roomType->name) · {{ $room->roomType->name }}@endif</div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('stay-request.store', $room) }}" class="dm-form" data-request-form novalidate>
        @csrf
        {{-- Honeypot (hidden from people) --}}
        <div class="dm-hp" aria-hidden="true">
            <label for="website">Site web</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <section class="dm-card">
            <h2><span>1</span> Formule</h2>
            <div class="dm-modes">
                @foreach ($modes as $mode)
                    <label class="dm-mode">
                        <input type="radio" name="pricing_mode" value="{{ $mode['value'] }}" data-rate="{{ $mode['rate'] }}" data-units="{{ $mode['units'] }}"
                            @checked(old('pricing_mode', count($modes) === 1 ? $mode['value'] : null) === $mode['value']) required>
                        <span class="dm-mode__box">
                            <span class="dm-mode__label">{{ $mode['label'] }}</span>
                            <span class="dm-mode__rate">{{ number_format($mode['rate'], 0, ',', ' ') }} FCFA <small>{{ $mode['unit'] }}</small></span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('pricing_mode')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <label class="form-label mt-3" for="duration">Durée <span class="text-muted" data-units-label></span></label>
            <div class="dm-stepper">
                <button type="button" class="btn btn-outline-secondary" data-step="-1" aria-label="Moins">−</button>
                <input type="number" inputmode="numeric" min="1" max="30" class="form-control text-center @error('duration') is-invalid @enderror" id="duration" name="duration" value="{{ old('duration', 1) }}" required>
                <button type="button" class="btn btn-outline-secondary" data-step="1" aria-label="Plus">+</button>
            </div>
            @error('duration')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

            <div class="dm-total" data-total aria-live="polite">Choisissez une formule.</div>
        </section>

        <section class="dm-card">
            <h2><span>2</span> Vos informations</h2>
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label" for="first_name">Prénom <span class="dm-optional">(facultatif)</span></label>
                    <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" maxlength="100">
                </div>
                <div class="col-6">
                    <label class="form-label" for="last_name">Nom <span class="dm-optional">(facultatif)</span></label>
                    <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" maxlength="100">
                </div>
                <div class="col-12">
                    <label class="form-label" for="phone">Téléphone <span class="dm-optional">(facultatif)</span></label>
                    <input type="tel" inputmode="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="07 00 00 00 00" maxlength="30">
                </div>
                <div class="col-6">
                    <label class="form-label" for="adults">Adultes</label>
                    <input type="number" inputmode="numeric" min="1" max="10" class="form-control" id="adults" name="adults" value="{{ old('adults', 1) }}" required>
                </div>
                <div class="col-6">
                    <label class="form-label" for="children">Enfants</label>
                    <input type="number" inputmode="numeric" min="0" max="10" class="form-control" id="children" name="children" value="{{ old('children', 0) }}" required>
                </div>
            </div>
        </section>

        <button type="submit" class="btn dm-btn dm-btn--lg w-100" data-submit>
            <i class="far fa-paper-plane"></i> Envoyer ma demande
        </button>
        <p class="dm-note">La chambre vous est réservée pendant {{ config('hotel.request_expiry_minutes') }} minutes, le temps de passer à la réception pour valider et régler.</p>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/demande.js') }}"></script>
@endpush
