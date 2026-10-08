@extends('admin.layouts.admin')

@section('title', 'Paramètres')

@section('content')
    <div class="admin-page-header">
        <h1>Paramètres de l'hôtel</h1>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="admin-card">
            <h5><i class="far fa-calendar-day me-2 text-primary"></i>Journée commerciale</h5>
            <p class="text-muted small">
                Une nouvelle journée commerciale commence à cette heure et se termine une minute avant, le lendemain.
                Elle sert aux chiffres « du jour » et « du mois » du tableau de bord, aux chambres réservées pour aujourd'hui
                et à la date (et l'année de numérotation) des factures. Exemple : avec un début à 06h00, une arrivée le 8 à 02h00
                compte pour la journée du 7.
            </p>
            <div class="row g-3 align-items-end">
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="business_day_start">Début de la journée</label>
                    <input type="time" class="form-control" id="business_day_start" name="business_day_start" value="{{ old('business_day_start', $settings['business_day_start']) }}" required>
                </div>
                <div class="col-md-8 col-lg-9">
                    <div class="alert alert-secondary mb-0 py-2">
                        Journée actuelle : de <strong>{{ str_replace(':', 'h', $settings['business_day_start']) }}</strong>
                        à <strong>{{ str_replace(':', 'h', $businessDayEnd) }}</strong> le lendemain.
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <h5><i class="far fa-clock me-2 text-primary"></i>Horaires des formules de tarification</h5>
            <p class="text-muted small">Utilisés pour calculer l'heure de départ et le montant à l'affectation d'une chambre.</p>
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="night_from">Nuitée : à partir de</label>
                    <input type="time" class="form-control" id="night_from" name="night_from" value="{{ old('night_from', $settings['night_from']) }}" required>
                    <div class="form-text">Heure à partir de laquelle une nuitée peut être prise.</div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="night_until">Nuitée : départ à</label>
                    <input type="time" class="form-control" id="night_until" name="night_until" value="{{ old('night_until', $settings['night_until']) }}" required>
                    <div class="form-text">Heure de départ le lendemain.</div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="day_hours">Journée : durée (heures)</label>
                    <input type="number" min="1" max="23" class="form-control" id="day_hours" name="day_hours" value="{{ old('day_hours', $settings['day_hours']) }}" required>
                    <div class="form-text">Forfait journée (ex. {{ $settings['day_hours'] }} h : entrée 8h00, sortie {{ \Illuminate\Support\Carbon::createFromTime(8)->addHours((int) $settings['day_hours'])->format('H\hi') }}).</div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="stay_min_hours">Séjour : durée d'une période (heures)</label>
                    <input type="number" min="1" max="168" class="form-control" id="stay_min_hours" name="stay_min_hours" value="{{ old('stay_min_hours', $settings['stay_min_hours']) }}" required>
                    <div class="form-text">Durée facturée par période de séjour.</div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <h5><i class="far fa-broom me-2 text-primary"></i>Ménage entre deux séjours</h5>
            <p class="text-muted small">Temps obligatoire entre la fin d'un séjour et le début du suivant dans la même chambre, pour toutes les affectations et réservations. 0 = aucun délai.</p>
            <div class="row g-3 align-items-end">
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="cleaning_buffer_minutes">Délai de ménage (minutes)</label>
                    <input type="number" min="0" max="240" step="5" class="form-control" id="cleaning_buffer_minutes" name="cleaning_buffer_minutes" value="{{ old('cleaning_buffer_minutes', $settings['cleaning_buffer_minutes']) }}" required>
                </div>
                <div class="col-md-8 col-lg-9">
                    <div class="alert alert-secondary mb-0 py-2">Exemple : un séjour qui se termine à 12h00 → le suivant peut commencer au plus tôt à <strong>{{ \Illuminate\Support\Carbon::createFromTime(12)->addMinutes((int) $settings['cleaning_buffer_minutes'])->format('H\hi') }}</strong>.</div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <h5><i class="far fa-mobile-alt me-2 text-primary"></i>Demandes des clients (depuis leur téléphone)</h5>
            <p class="text-muted small">
                Un client qui arrive peut choisir une chambre libre depuis son téléphone (page <a href="{{ route('stay-request.index') }}" target="_blank" rel="noopener">{{ route('stay-request.index') }}</a>).
                Sa demande retient la chambre pendant ce délai, le temps que la réception la valide et encaisse. Passé ce délai, la chambre est libérée.
                Affiche à imprimer : <a href="{{ route('admin.stay-requests.qr') }}">QR code client</a>.
            </p>
            <div class="row g-3 align-items-end">
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="request_expiry_minutes">Délai de traitement (minutes)</label>
                    <input type="number" min="5" max="240" step="5" class="form-control" id="request_expiry_minutes" name="request_expiry_minutes" value="{{ old('request_expiry_minutes', $settings['request_expiry_minutes']) }}" required>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <h5><i class="far fa-receipt me-2 text-primary"></i>Reçu client (imprimante thermique)</h5>
            <p class="text-muted small">Imprimé après une affectation ou une réservation, et réimprimable depuis la fiche.</p>
            <div class="row g-3">
                <div class="col-md-4 col-xl-3">
                    <label class="form-label d-block">Largeur du rouleau</label>
                    <div class="btn-group w-100" role="group">
                        @foreach ([58, 80] as $width)
                            <input type="radio" class="btn-check" name="receipt_width" id="receipt_width_{{ $width }}" value="{{ $width }}" @checked((int) old('receipt_width', $settings['receipt_width']) === $width)>
                            <label class="btn btn-outline-primary" for="receipt_width_{{ $width }}">{{ $width }} mm</label>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-8 col-xl-9">
                    <label class="form-label" for="receipt_footer">Message de bas de reçu</label>
                    <input type="text" class="form-control" id="receipt_footer" name="receipt_footer" maxlength="200" value="{{ old('receipt_footer', $settings['receipt_footer']) }}">
                </div>
                <div class="col-12">
                    <div class="alert alert-secondary mb-0 py-2">
                        <i class="far fa-info-circle me-1"></i> Le nom, l'adresse, les téléphones et les mentions légales imprimés sur le reçu se règlent dans
                        <a href="{{ route('admin.hotel.edit') }}">Configuration → Hôtel</a>.
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Enregistrer les paramètres</button>
    </form>
@endsection
