@extends('layouts.hotel')

@section('title', 'Chambre Standard - Espace Katioka')

@section('content')

<x-page-hero title="Chambre Standard" subtitle="Un excellent choix pour un séjour reposant en famille ou entre amis" breadcrumb="Détails d'une chambre" />

<section class="luxivo-room-details-sec pt-110 pb-120">
    <div class="container">
        <div class="room-details-wrapper">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="room-content-area mb-30">
                        <h3 class="title">Chambre Standard</h3>
                        <div class="rooms-ratings-quote d-flex align-items-center">
                            <div class="ratings">
                                <a href="#">(56 avis)</a>
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                            </div>
                            <span><i class="far fa-map-marker-alt mx-2"></i>Bouaké</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="share-button text-md-end mb-40">
                        <a href="{{ route('rooms.show') }}" class="theme-btn style-two">Partager<i class="far fa-share-alt"></i></a>
                    </div>
                </div>
            </div>
            <div class="rooms-meta-info mb-25">
                <div class="meta-box text-center mb-30">
                    <img src="{{ asset('assets/images/innerpage/icon/icon-12.png') }}" class="mb-15" alt="icône">
                    <h5>3 personnes</h5>
                </div>
                <div class="meta-box text-center mb-30">
                    <img src="{{ asset('assets/images/innerpage/icon/icon-13.png') }}" class="mb-15" alt="icône">
                    <h5>3 enfants</h5>
                </div>
                <div class="meta-box text-center mb-30">
                    <img src="{{ asset('assets/images/innerpage/icon/icon-14.png') }}" class="mb-15" alt="icône">
                    <h5>40 m²</h5>
                </div>
                <div class="meta-box text-center mb-30">
                    <img src="{{ asset('assets/images/innerpage/icon/icon-15.png') }}" class="mb-15" alt="icône">
                    <h5>2 lits</h5>
                </div>
                <div class="meta-box text-center mb-30">
                    <img src="{{ asset('assets/images/innerpage/icon/icon-16.png') }}" class="mb-15" alt="icône">
                    <h5>2 salles de bain</h5>
                </div>
                <div class="meta-box text-center mb-30">
                    <img src="{{ asset('assets/images/innerpage/icon/icon-17.png') }}" class="mb-15" alt="icône">
                    <h5>75 000 FCFA / nuit</h5>
                </div>
            </div>
            <div class="room-image mb-40">
                <img src="{{ asset('assets/images/stock/room-3.jpg') }}" class="mb-15" alt="chambre">
            </div>
            <div class="row">
                <div class="col-xl-8">
                    <div class="room-content-box">
                        <h3>Description de la chambre</h3>
                        <p>Cette chambre Standard, à la décoration élégante, propose soit deux lits simples, soit un grand lit Queen Size. Un lit d'appoint peut être installé dans le salon spacieux. Profitez d'une vue apaisante et d'une lumière douce en fin de journée depuis le balcon orienté sud.</p>
                        <p>Chaque chambre est décorée avec soin et dotée d'une literie confortable, dans un cadre moderne. Elle donne également accès à notre espace bien-être et à notre salon, où vous pourrez vous détendre et profiter de rafraîchissements à tout moment.</p>
                    </div>
                    <div class="room-content-box mb-25">
                        <h3>Règles de la chambre</h3>
                        <ul class="check-list style-two">
                            <li>Heure d'arrivée : à partir de 15h00</li>
                            <li>Heure de départ : avant 11h00</li>
                            <li>Arrivée anticipée / départ tardif : selon disponibilité, des frais supplémentaires peuvent s'appliquer.</li>
                            <li>Capacité maximale : merci de respecter la capacité indiquée pour la chambre.</li>
                            <li>Toutes les chambres sont non-fumeurs. Des frais de nettoyage s'appliquent en cas de non-respect.</li>
                            <li>Le ménage quotidien est assuré sauf demande contraire.</li>
                            <li>Les demandes de literie ou d'équipements supplémentaires doivent être faites à l'avance.</li>
                        </ul>
                    </div>
                    <div class="room-content-box mb-25">
                        <h3>Services & équipements</h3>
                        <div class="services-list">
                            <div class="row">
                                <div class="col-md-4"><div class="box"><h5><i class="far fa-wifi"></i>Wi-Fi gratuit</h5></div></div>
                                <div class="col-md-4"><div class="box"><h5><i class="far fa-tv-alt"></i>Télévision connectée</h5></div></div>
                                <div class="col-md-4"><div class="box"><h5><i class="far fa-swimmer"></i>Piscine</h5></div></div>
                            </div>
                        </div>
                        <div class="services-list">
                            <div class="row">
                                <div class="col-md-4"><div class="box"><h5><i class="far fa-dumbbell"></i>Salle de sport</h5></div></div>
                                <div class="col-md-4"><div class="box"><h5><i class="far fa-bed"></i>Lit King Size</h5></div></div>
                                <div class="col-md-4"><div class="box"><h5><i class="far fa-headset"></i>Assistance 24/7</h5></div></div>
                            </div>
                        </div>
                        <div class="services-list">
                            <div class="row">
                                <div class="col-md-4"><div class="box"><h5><i class="far fa-shower"></i>Douche</h5></div></div>
                                <div class="col-md-4"><div class="box"><h5><i class="far fa-refrigerator"></i>Réfrigérateur</h5></div></div>
                                <div class="col-md-4"><div class="box"><h5><i class="far fa-plane-arrival"></i>Transfert aéroport</h5></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="room-content-box">
                        <h3>Politique d'annulation :</h3>
                        <p>Les annulations effectuées 48 heures ou plus avant la date d'arrivée bénéficient d'un remboursement intégral. Le montant total de la réservation est débité au moment de la réservation.</p>
                    </div>
                    <div class="room-content-box mb-35 spacing-gap">
                        <h3>Caractéristiques de la chambre</h3>
                        <div class="row mb-15">
                            <div class="col-lg-4">
                                <ul class="check-list style-two mb-20">
                                    <li>Lits d'appoint pour enfants</li>
                                    <li>Bouilloire / cafetière</li>
                                </ul>
                            </div>
                            <div class="col-lg-4">
                                <ul class="check-list style-two mb-20">
                                    <li>Climatisation réglable</li>
                                    <li>Literie haut de gamme</li>
                                </ul>
                            </div>
                            <div class="col-lg-4">
                                <ul class="check-list style-two mb-20">
                                    <li>Décoration soignée</li>
                                    <li>Équipements connectés</li>
                                </ul>
                            </div>
                        </div>
                        <img src="{{ asset('assets/images/stock/bathroom-2.jpg') }}" alt="chambre">
                        <p>Nos chambres et suites, aménagées avec soin, offrent un confort optimal et un style raffiné. Chaque chambre propose des équipements modernes et des attentions particulières pour un séjour relaxant.</p>
                    </div>
                    <div class="room-content-box">
                        <h3>Galerie de la chambre</h3>
                        <div class="room-gallery-slider">
                            <div class="gallery-image"><img src="{{ asset('assets/images/stock/room-4.jpg') }}" alt="chambre"></div>
                            <div class="gallery-image"><img src="{{ asset('assets/images/stock/room-5.jpg') }}" alt="chambre"></div>
                            <div class="gallery-image"><img src="{{ asset('assets/images/stock/room-8.jpg') }}" alt="chambre"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="room-sidebar-area sticky-widget mt-5 mt-xl-0">
                        <div class="room-sidebar-widget room-booking-widget mb-40">
                            <h4 class="widget-title">Réservez votre chambre</h4>
                            <div class="widget-content">
                                <form>
                                    <div class="form-group">
                                        <label>Arrivée</label>
                                        <input type="date" class="form_control" name="checkin">
                                    </div>
                                    <div class="form-group">
                                        <label>Départ</label>
                                        <input type="date" class="form_control" name="checkout">
                                    </div>
                                    <div class="form-group">
                                        <label>Chambres</label>
                                        <select class="wide">
                                            <option>01 chambre</option>
                                            <option>02 chambres</option>
                                            <option>03 chambres</option>
                                            <option>04 chambres</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Adultes</label>
                                        <select class="wide">
                                            <option>02 adultes</option>
                                            <option>04 adultes</option>
                                            <option>06 adultes</option>
                                            <option>08 adultes</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Enfants</label>
                                        <select class="wide">
                                            <option>01 enfant</option>
                                            <option>02 enfants</option>
                                            <option>03 enfants</option>
                                            <option>04 enfants</option>
                                        </select>
                                    </div>
                                    <div class="extra-service">
                                        <h5>Services complémentaires</h5>
                                        <div class="form-check">
                                            <label class="form-check-label" for="check1">
                                                <input class="form-input-check" type="checkbox" name="check" id="check1">
                                                Ménage supplémentaire<span>7 500 FCFA / nuit</span>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label" for="check2">
                                                <input class="form-input-check" type="checkbox" name="check" id="check2">
                                                Parking<span>Gratuit</span>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label" for="check3">
                                                <input class="form-input-check" type="checkbox" name="check" id="check3">
                                                Salle de sport & spa<span>10 000 FCFA / séjour</span>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label" for="check4">
                                                <input class="form-input-check" type="checkbox" name="check" id="check4">
                                                Dîner<span>12 500 FCFA / jour</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <button class="theme-btn style-one" type="button">RÉSERVER<i class="far fa-arrow-right"></i></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="room-sidebar-widget room-support-widget mb-40">
                            <h4 class="widget-title">Assistance réservation</h4>
                            <div class="widget-content">
                                <ul>
                                    @foreach ($hotelInfo->phones() as $phone)
                                        <li><a href="{{ $hotelInfo->telHref($phone) }}">Tél : {{ $phone }}</a></li>
                                    @endforeach
                                    @if ($hotelInfo->get('email'))
                                        <li><a href="mailto:{{ $hotelInfo->get('email') }}">{{ $hotelInfo->get('email') }}</a></li>
                                    @endif
                                </ul>
                                <h3>Adresse</h3>
                                <p>{{ implode(', ', array_filter([$hotelInfo->name(), $hotelInfo->fullAddress()])) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
