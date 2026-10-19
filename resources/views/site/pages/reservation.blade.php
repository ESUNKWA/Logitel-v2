@extends('site.layouts.app')

@section('title', 'Réserver - '.$hotelInfo->name())
@section('meta_description', 'Demandez votre réservation à '.$hotelInfo->name().' : la réception vous rappelle pour confirmer.')

@section('content')

<x-site::page-hero title="Réserver" subtitle="Votre demande de réservation" breadcrumb="Réserver" />

{{-- Online booking request: lands in the application (Réservations → Demandes en ligne).
     Prices are the room types' (indicative); public/site/js/reservation.js shows the estimate. --}}
<section class="site-booking pt-110 pb-110">
    <div class="container">
        @if ($types->isEmpty())
            <div class="site-empty text-center">
                <i class="far fa-calendar-alt"></i>
                <h4>La réservation en ligne sera bientôt disponible</h4>
                <p>En attendant, contactez la réception pour réserver.</p>
                <a href="{{ route('contact') }}" class="theme-btn style-one mt-3">Nous contacter <i class="far fa-arrow-right"></i></a>
            </div>
        @else
            {{-- The website offers the séjour only (PricingMode::WEBSITE, client decision 2026-10-19). --}}
            @php($mode = \App\Enums\PricingMode::WEBSITE)
            <form method="POST" action="{{ route('booking.store') }}" class="site-booking__form" data-booking
                data-rates='@json($rates)' data-night-from="{{ config('hotel.night.from') }}" data-night-until="{{ config('hotel.night.until') }}"
                data-day-hours="{{ config('hotel.day_hours') }}" data-stay-hours="{{ config('hotel.stay_min_hours') }}" novalidate>
                @csrf
                <div class="site-booking__hp" aria-hidden="true">
                    <label for="website">Ne pas remplir</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                @if ($errors->any())
                    <div class="site-booking__errors" role="alert">
                        <strong>Votre demande n'a pas pu être envoyée :</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-lg-8">
                        <h4 class="site-booking__step"><span>1</span> Type de chambre</h4>
                        <div class="site-booking__choices">
                            @foreach ($types as $type)
                                @php($lowest = collect($rates[$type->id])->filter()->min())
                                <label class="site-booking__choice">
                                    <input type="radio" name="room_type_id" value="{{ $type->id }}" data-type @checked((int) $selectedType === $type->id)>
                                    <span class="site-booking__choice-body">
                                        <strong>{{ $type->name }}</strong>
                                        <small>Jusqu'à {{ $type->capacity_adults }} adulte{{ $type->capacity_adults > 1 ? 's' : '' }}@if ($type->capacity_children) et {{ $type->capacity_children }} enfant{{ $type->capacity_children > 1 ? 's' : '' }}@endif</small>
                                        <em>dès {{ number_format($lowest, 0, ',', ' ') }} FCFA</em>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <h4 class="site-booking__step"><span>2</span> Dates du séjour</h4>
                        {{-- One fixed formula: the radio stays (checked, hidden) for reservation.js's estimate. --}}
                        <div class="site-booking__modes site-booking__modes--single">
                            <label class="site-booking__mode">
                                <input type="radio" name="pricing_mode" value="{{ $mode->value }}" data-mode data-unit="{{ $mode->rateUnit() }}" data-units="{{ $mode->unitPlural() }}" checked class="visually-hidden">
                                <span>{{ $mode->getLabel() }}<small data-mode-price></small></span>
                            </label>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="site-booking__label" for="check_in" data-check-in-label>Arrivée le</label>
                                <input type="datetime-local" class="form_control" id="check_in" name="check_in" value="{{ old('check_in', now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i')) }}" min="{{ today()->toDateString() }}" required data-check-in>
                            </div>
                            <div class="col-md-6">
                                <label class="site-booking__label" for="duration">Nombre de <span data-units-label>{{ $mode->unitPlural() }}</span></label>
                                <input type="number" class="form_control" id="duration" name="duration" min="1" max="30" value="{{ old('duration', 1) }}" required data-duration>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="site-booking__label" for="adults">Adultes</label>
                                <input type="number" class="form_control" id="adults" name="adults" min="1" max="10" value="{{ old('adults', 1) }}" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="site-booking__label" for="children">Enfants</label>
                                <input type="number" class="form_control" id="children" name="children" min="0" max="10" value="{{ old('children', 0) }}" required>
                            </div>
                        </div>

                        <h4 class="site-booking__step"><span>3</span> Vos coordonnées</h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="site-booking__label" for="phone">Téléphone <span class="text-danger">*</span></label>
                                <input type="tel" class="form_control" id="phone" name="phone" value="{{ old('phone') }}" required autocomplete="tel" placeholder="Ex. 07 00 00 00 00">
                            </div>
                            <div class="col-md-6">
                                <label class="site-booking__label" for="email">E-mail</label>
                                <input type="email" class="form_control" id="email" name="email" value="{{ old('email') }}" autocomplete="email">
                            </div>
                            <div class="col-md-6">
                                <label class="site-booking__label" for="first_name">Prénom</label>
                                <input type="text" class="form_control" id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name">
                            </div>
                            <div class="col-md-6">
                                <label class="site-booking__label" for="last_name">Nom</label>
                                <input type="text" class="form_control" id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name">
                            </div>
                            <div class="col-12">
                                <label class="site-booking__label" for="message">Message (facultatif)</label>
                                <textarea class="form_control" id="message" name="message" rows="3" maxlength="1000" placeholder="Heure d'arrivée, demande particulière…">{{ old('message') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="site-booking__summary sticky-widget">
                            <h4>Votre demande</h4>
                            <dl data-summary>
                                <dt>Chambre</dt><dd data-sum-type>—</dd>
                                <dt>Formule</dt><dd data-sum-mode>—</dd>
                                <dt>Arrivée</dt><dd data-sum-start>—</dd>
                                <dt>Départ prévu</dt><dd data-sum-end>—</dd>
                            </dl>
                            <div class="site-booking__total">
                                <span>Montant estimé</span>
                                <strong data-sum-total>—</strong>
                            </div>
                            <p class="site-booking__note">Ce n'est pas encore une réservation : la réception vérifie la disponibilité et <strong>vous rappelle pour confirmer</strong>. Aucun paiement en ligne.</p>
                            <button type="submit" class="theme-btn style-one w-100">Envoyer ma demande <i class="far fa-arrow-right"></i></button>
                        </div>
                    </div>
                </div>
            </form>
        @endif
    </div>
</section>

@endsection

@push('scripts')
    <script src="{{ asset('site/js/reservation.js') }}"></script>
@endpush
