@extends('layouts.hotel')

@section('title', 'Espace Katioka - Hôtel & Resort')
@section('meta_description', 'Espace Katioka - Hôtel, Resort, Chambres')

@section('content')

<section class="luxivo-hero">
    <div class="luxivo-hero_one p-r z-1 bg_cover" data-src="{{ asset('assets/images/stock/hero-bg.jpg') }}">
        <div class="hero-image hero-image_one" data-aos="fade-up" data-aos-duration="2500" data-aos-delay="10"><img src="{{ asset('assets/images/stock/hero-1.jpg') }}" alt="hero"></div>
        <div class="hero-image hero-image_two" data-aos="fade-down" data-aos-duration="3000" data-aos-delay="20"><img src="{{ asset('assets/images/stock/hero-2.jpg') }}" alt="hero"></div>
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="hero-content text-center text-xl-start mb-25">
                        <h1 data-aos="fade-left" data-aos-duration="2500">Notre hôtel & resort <br> de luxe</h1>
                    </div>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="text-box" data-aos="fade-up" data-aos-duration="2600">
                        <p>Plongez dans le luxe et la sérénité avec des vues imprenables, dans des chambres élégantes pensées pour votre confort à Espace Katioka.</p>
                        <div class="hero-button">
                            <a href="{{ route('rooms.index') }}" class="theme-btn style-one">Découvrir nos chambres<i class="far fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="luxivo-avatar-box style-one" data-aos="fade-up" data-aos-duration="2000">
                <h6>De belles destinations</h6>
                <div class="avatar-text-box">
                    <div class="avatar-list">
                        <ul>
                            <li><img src="{{ asset('assets/images/stock/testimonial-1.jpg') }}" alt="avatar"></li>
                            <li><img src="{{ asset('assets/images/stock/testimonial-2.jpg') }}" alt="avatar"></li>
                            <li><img src="{{ asset('assets/images/stock/team-1.jpg') }}" alt="avatar"></li>
                            <li><span>10+</span></li>
                        </ul>
                    </div>
                    <div class="text">100k+ visiteurs ont découvert notre établissement</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="luxivo-search_one pb-60">
    <div class="container">
        <div class="luxivo-search-wrapper">
            <div class="shape shape-one"><img src="{{ asset('assets/images/home-one/gallery/shape1.png') }}" alt="shape"></div>
            <div class="shape shape-two"><img src="{{ asset('assets/images/home-one/gallery/shape2.png') }}" alt="shape"></div>
            <div class="shape shape-three"><img src="{{ asset('assets/images/home-one/gallery/shape3.png') }}" alt="shape"></div>
            <div class="row">
                <div class="col-lg-12">
                    <div class="text-box text-center text-xl-start">
                        <h3 class="mb-20">Trouvez la meilleure formule</h3>
                    </div>
                </div>
            </div>
            <form>
                <div class="row align-items-end">
                    <div class="col-xl-10">
                        <div class="row">
                            <div class="col-xl-3 col-md-6">
                                <div class="form-group">
                                    <label>Arrivée</label>
                                    <input type="date" class="form_control" name="CheckIn">
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="form-group">
                                    <label>Départ</label>
                                    <input type="date" class="form_control" name="CheckOut">
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="form-group">
                                    <label>Chambres</label>
                                    <select class="wide">
                                        <option>01 chambre</option>
                                        <option>02 chambres</option>
                                        <option>03 chambres</option>
                                        <option>04 chambres</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="form-group">
                                    <label>Voyageurs</label>
                                    <select class="wide">
                                        <option>02 adultes, 0 enfant</option>
                                        <option>02 adultes, 1 enfant</option>
                                        <option>04 adultes, 2 enfants</option>
                                        <option>06 adultes, 0 enfant</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2">
                        <div class="luxivo-button mt-4 mt-xl-0 text-center text-xl-start">
                            <button class="theme-btn style-one" type="button">VÉRIFIER</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<section class="luxivo-about_one pt-120 pb-120">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-9">
                <div class="luxivo-image-box">
                    <div class="luxivo-image image_one" data-aos="fade-up" data-aos-duration="800">
                        <img src="{{ asset('assets/images/stock/about-1.jpg') }}" alt="à propos">
                    </div>
                    <div class="luxivo-image image_two" data-aos="fade-up" data-aos-duration="1000">
                        <img src="{{ asset('assets/images/stock/about-2.jpg') }}" alt="à propos">
                    </div>
                    <div class="luxivo-customer-box" data-aos="fade-up" data-aos-duration="1000">
                        <div class="content">
                            <h3>8k+</h3>
                            <p>Clients satisfaits</p>
                        </div>
                    </div>
                    <div class="luxivo-experience-box" data-aos="fade-down" data-aos-duration="1000">
                        <div class="content">
                            <h3>30+</h3>
                            <p>Années d'expérience</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-lg-9">
                <div class="luxivo-content-box mt-5 mt-xl-0">
                    <div class="section-title">
                        <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                        <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">À propos de notre hôtel</span>
                        <h2 data-aos="fade-up" data-aos-duration="1200">Séjournez dans un cadre luxueux, où que vous soyez.</h2>
                    </div>
                    <p>Espace Katioka est bien plus qu'un hôtel de luxe ; c'est une oasis de tranquillité. Grâce à son emplacement exceptionnel, ses hébergements raffinés et une offre de restauration soignée, il offre à ses hôtes une expérience inoubliable.</p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="luxivo-iconic-left-box style-one mb-40" data-aos="fade-up" data-aos-duration="1000">
                                <div class="icon"><img src="{{ asset('assets/images/home-one/icon/icon1.png') }}" alt="icône"></div>
                                <div class="content"><h5>Une ambiance soignée</h5></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="luxivo-iconic-left-box style-one mb-40" data-aos="fade-up" data-aos-duration="1200">
                                <div class="icon"><img src="{{ asset('assets/images/home-one/icon/icon2.png') }}" alt="icône"></div>
                                <div class="content"><h5>Piscine et détente</h5></div>
                            </div>
                        </div>
                    </div>
                    <ul class="check-list style-one mb-40" data-aos="fade-up" data-aos-duration="1400">
                        <li><i class="fas fa-badge-check"></i>Un emplacement de choix avec de belles vues</li>
                        <li><i class="fas fa-badge-check"></i>Des chambres pensées pour votre confort</li>
                    </ul>
                    <div class="luxivo-button-wrap" data-aos="fade-up" data-aos-duration="1600">
                        <div class="luxivo-button">
                            <a href="{{ route('about') }}" class="theme-btn style-one">EN SAVOIR PLUS <i class="far fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="luxivo-room_one p-r z-1 pt-120 pb-120">
    <div class="room-bg" data-src="{{ asset('assets/images/stock/hero-bg.jpg') }}"></div>
    <div class="container">
        <div class="row align-items-end">
            <div class="col-xl-6 col-lg-8">
                <div class="section-title mb-45">
                    <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                    <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">Chambres & Suites</span>
                    <h2 data-aos="fade-up" data-aos-duration="1200">Reposez-vous dans le confort, choisissez parmi nos suites & chambres</h2>
                </div>
            </div>
            <div class="col-xl-6 col-lg-4">
                <div class="room-arrows mb-60"></div>
            </div>
        </div>
        <div class="room-slider" data-aos="fade-up" data-aos-duration="1000">
            @foreach([
                ['img' => 'room-3.jpg', 'title' => 'Chambres Suites Signature', 'price' => '75 000 FCFA'],
                ['img' => 'room-4.jpg', 'title' => 'Chambres Deluxe Exclusives', 'price' => '95 000 FCFA'],
                ['img' => 'room-5.jpg', 'title' => 'Chambres Deluxe Familiales', 'price' => '135 000 FCFA'],
                ['img' => 'room-8.jpg', 'title' => 'Suites Doubles', 'price' => '210 000 FCFA'],
                ['img' => 'bathroom-2.jpg', 'title' => 'Chambres Deluxe Exclusives', 'price' => '225 000 FCFA'],
            ] as $room)
            <div class="luxivo-room-item style-one">
                <div class="room-image">
                    <img src="{{ asset('assets/images/stock/'.$room['img']) }}" alt="{{ $room['title'] }}">
                    <div class="rate"><i class="fas fa-star"></i>4.9</div>
                </div>
                <div class="room-content">
                    <h4 class="title"><a href="{{ route('rooms.show') }}">{{ $room['title'] }}</a></h4>
                    <p>Profitez d'un séjour serein dans une chambre élégamment aménagée, pensée pour votre confort.</p>
                    <div class="room-meta">
                        <span><i class="far fa-expand-arrows-alt"></i>250 sqm</span>
                        <span><i class="far fa-bed-alt"></i>3 lits</span>
                        <span><i class="far fa-bath"></i>3 salles de bain</span>
                    </div>
                    <div class="room-bottom">
                        <div class="price">{{ $room['price'] }}<span>/ nuit</span></div>
                        <a href="{{ route('rooms.show') }}" class="theme-btn style-two">VOIR DÉTAILS<i class="far fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="luxivo-facilities_one pt-120 pb-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-5 col-lg-9">
                <div class="luxivo-image-box">
                    <div class="luxivo-image image_one" data-aos="fade-up" data-aos-duration="800">
                        <img src="{{ asset('assets/images/stock/facilities-1.jpg') }}" alt="équipements">
                        <div class="overlay"></div>
                        <div class="shape"><img src="{{ asset('assets/images/home-one/gallery/f-shape.png') }}" alt="forme"></div>
                    </div>
                    <div class="luxivo-image image_two" data-aos="fade-up" data-aos-duration="1000">
                        <img src="{{ asset('assets/images/stock/facilities-2.jpg') }}" alt="équipements">
                    </div>
                </div>
            </div>
            <div class="col-xl-7 col-lg-9">
                <div class="luxivo-content-box mt-5 mt-xl-0">
                    <div class="section-title">
                        <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                        <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">Équipements de l'hôtel</span>
                        <h2 data-aos="fade-up" data-aos-duration="1200">Tout ce qu'il faut pour un séjour reposant et confortable.</h2>
                    </div>
                    <p data-aos="fade-up" data-aos-duration="1400">Votre sécurité et votre bien-être sont notre priorité. Nous appliquons des protocoles d'hygiène stricts et une surveillance continue pour votre tranquillité d'esprit tout au long de votre séjour.</p>
                    <div class="iconic-box-wrapper">
                        <div class="row">
                            <div class="col-md-6 item-column">
                                <div class="luxivo-iconic-left-box style-two" data-aos="fade-up" data-aos-duration="1600">
                                    <div class="icon"><img src="{{ asset('assets/images/home-one/icon/icon3.png') }}" alt="icône"></div>
                                    <div class="content"><h4>Salle de sport</h4></div>
                                </div>
                            </div>
                            <div class="col-md-6 item-column">
                                <div class="luxivo-iconic-left-box style-two" data-aos="fade-up" data-aos-duration="1800">
                                    <div class="icon"><img src="{{ asset('assets/images/home-one/icon/icon4.png') }}" alt="icône"></div>
                                    <div class="content"><h4>Restaurant & Bar</h4></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <ul class="check-list style-one" data-aos="fade-up" data-aos-duration="2000">
                        <li><i class="fas fa-badge-check"></i>Un emplacement de choix avec de belles vues</li>
                        <li><i class="fas fa-badge-check"></i>Des chambres pensées pour votre confort</li>
                        <li><i class="fas fa-badge-check"></i>Un accueil chaleureux à tout moment</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="luxivo-features_one bg_cover pt-110 pb-120" data-src="{{ asset('assets/images/stock/facilities-1.jpg') }}">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6">
                <div class="section-title text-white text-center mb-60">
                    <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('assets/images/home-one/gallery/d-line-wt.png') }}" alt="ligne"></span>
                    <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">Découvrez tous nos</span>
                    <h2 data-aos="fade-up" data-aos-duration="1200">Équipements phares</h2>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="features-slider" data-aos="fade-up" data-aos-duration="1000">
            @foreach([
                ['icon' => 'icon-5.png', 'title' => 'Climatisation', 'text' => "Nos chambres et suites sont équipées d'une climatisation moderne et économe en énergie."],
                ['icon' => 'icon-6.png', 'title' => 'Internet haut débit', 'text' => "Profitez d'un Wi-Fi rapide et fiable dans tout l'établissement."],
                ['icon' => 'icon-7.png', 'title' => 'Serrure connectée', 'text' => "Accédez à votre chambre 24h/24 grâce à un accès numérique personnalisé."],
                ['icon' => 'icon-8.png', 'title' => 'Salle de sport', 'text' => "Notre salle de sport entièrement équipée vous accueille à toute heure."],
                ['icon' => 'icon-6.png', 'title' => 'Internet haut débit', 'text' => "Profitez d'un Wi-Fi rapide et fiable dans tout l'établissement."],
            ] as $feature)
            <div class="luxivo-feature-item style-one">
                <div class="icon"><img src="{{ asset('assets/images/home-one/icon/'.$feature['icon']) }}" class="main-icon" alt="icône"></div>
                <div class="content">
                    <h4>{{ $feature['title'] }}</h4>
                    <p>{{ $feature['text'] }}</p>
                    <a href="{{ route('rooms.show') }}" class="theme-btn style-two">Réserver<i class="far fa-arrow-right"></i></a>
                </div>
            </div>
            @endforeach
        </div>
        <div class="features-arrows mt-60" data-aos="fade-up" data-aos-duration="1200"></div>
    </div>
</section>

<section class="luxivo-offer-sec pt-110 pb-60">
    <div class="container">
        <div class="row align-items-end">
            <div class="col-lg-8">
                <div class="section-title text-lg-start text-center mb-50">
                    <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                    <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">Offre spéciale</span>
                    <h2 data-aos="fade-up" data-aos-duration="1200">Les meilleures offres de notre hôtel</h2>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="luxivo-button text-lg-end text-center mb-60" data-aos="fade-up" data-aos-duration="1000">
                    <a href="{{ route('offers.index') }}" class="theme-btn style-one">VOIR TOUTES LES OFFRES<i class="far fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <div class="luxivo-offer-wrapper">
            @foreach([
                ['img' => 'gallery-1.jpg', 'title' => 'Rattrapez vos moments manqués et célébrez en beauté', 'text' => 'Découvrez des économies exceptionnelles et des forfaits sur mesure pour un séjour inoubliable. Réservez dès maintenant.'],
                ['img' => 'gallery-3.jpg', 'title' => 'Profitez de réductions exclusives sur nos chambres et suites', 'text' => "Profitez d'offres à durée limitée et vivez le luxe moderne au meilleur prix."],
                ['img' => 'gallery-4.jpg', 'title' => 'Économisez sur votre prochaine escapade avec nos offres saisonnières', 'text' => "Que ce soit pour un week-end ou un séjour prolongé, nous avons l'offre parfaite pour vous."],
            ] as $i => $offer)
            <div class="luxivo-offer-item style-one mb-60" data-aos="fade-up" data-aos-duration="{{ 1000 + $i * 200 }}">
                <div class="row">
                    <div class="col-lg-5">
                        <div class="content">
                            <div class="shape"><img src="{{ asset('assets/images/home-one/gallery/off-shape.png') }}" alt="forme"></div>
                            <h3 class="title"><a href="{{ route('offers.show') }}">{{ $offer['title'] }}</a></h3>
                            <div class="ratings">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                <span>4.9 avis</span>
                            </div>
                            <p>{{ $offer['text'] }}</p>
                            <div class="offer-footer">
                                <div class="price">175 000 FCFA<span>Expire le 31 août</span></div>
                                <a href="{{ route('offers.show') }}" class="theme-btn style-two">VOIR L'OFFRE <i class="far fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="thumbnail"><img src="{{ asset('assets/images/stock/'.$offer['img']) }}" alt="offre"></div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="luxivo-counter_one bg_cover pt-110 pb-70" data-src="{{ asset('assets/images/stock/footer-bg.jpg') }}">
    <div class="container">
        <div class="row">
            @foreach([['635', '+', 'Chambres de luxe'], ['85', 'k+', 'Clients heureux'], ['2.3', 'k+', 'Avis cinq étoiles'], ['2.8', 'm', 'Petits-déjeuners servis']] as $stat)
            <div class="col-lg-3 col-sm-6">
                <div class="luxivo-counter-item style-one mb-40">
                    <div class="content">
                        <h2><span class="counter">{{ $stat[0] }}</span>{{ $stat[1] }}</h2>
                        <p>{{ $stat[2] }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="luxivo-testimonial-one pt-110 pb-120">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6">
                <div class="section-title mb-50 text-center">
                    <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                    <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">Témoignages</span>
                    <h2 data-aos="fade-up" data-aos-duration="1200">Ce que disent nos clients</h2>
                </div>
            </div>
        </div>
        <div class="testimonial-slider" data-aos="fade-up" data-aos-duration="1000">
            @foreach([
                ['img' => 'testimonial-1.jpg', 'name' => 'Aïcha Koné', 'role' => 'Cliente fidèle'],
                ['img' => 'testimonial-2.jpg', 'name' => 'Serge Yao', 'role' => 'Voyageur d\'affaires'],
                ['img' => 'team-3.jpg', 'name' => 'Nadège Bamba', 'role' => 'Cliente fidèle'],
            ] as $t)
            <div class="luxivo-testimonial-item style-one">
                <div class="testimonial-content">
                    <div class="ratings-quote-wrap">
                        <div class="quote"><i class="fas fa-quote-left"></i></div>
                        <div class="ratings"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    </div>
                    <p>"Un séjour à Espace Katioka, c'est le luxe et la sérénité réunis. Le personnel est aux petits soins et les chambres sont d'un confort remarquable."</p>
                    <div class="author-thumb-item">
                        <div class="author-thumb"><img src="{{ asset('assets/images/stock/'.$t['img']) }}" alt="{{ $t['name'] }}"></div>
                        <div class="author-info">
                            <h5>{{ $t['name'] }}</h5>
                            <span class="position">{{ $t['role'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="luxivo-contact_one pt-60 pb-60">
    <div class="contact-bg-left bg_cover" data-src="{{ asset('assets/images/stock/about-1.jpg') }}" data-aos="fade-up" data-aos-duration="800">
        <div class="luxivo-info-box">
            <div class="content">
                <span class="line d-block mb-10"><img src="{{ asset('assets/images/home-one/contact/d-line.png') }}" alt="ligne"></span>
                @if ($hotelInfo->get('phone'))
                    <span>Appelez-nous pour toute demande</span>
                    <h3><a href="{{ $hotelInfo->telHref($hotelInfo->get('phone')) }}">{{ $hotelInfo->get('phone') }}</a></h3>
                @else
                    <span>Une question, une demande ?</span>
                    <h3><a href="{{ route('contact') }}">Contactez-nous</a></h3>
                @endif
            </div>
        </div>
    </div>
    <div class="contact-bg-right bg_cover" data-src="{{ asset('assets/images/stock/gallery-2.jpg') }}"></div>
    <div class="container">
        <div class="row justify-content-end">
            <div class="col-xl-7">
                <div class="booking-wrapper">
                    <div class="section-title mb-50">
                        <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                        <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">Contactez-nous</span>
                        <h2 data-aos="fade-up" data-aos-duration="1200">Faisons de votre séjour un moment inoubliable</h2>
                        <p data-aos="fade-up" data-aos-duration="1400">Une question, besoin d'aide pour une réservation ou envie d'une expérience sur mesure ? Notre équipe n'est qu'à un message.</p>
                    </div>
                    <form class="booking-form" data-aos="fade-up" data-aos-duration="1600">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Nom</label>
                                    <input type="text" class="form_control" placeholder="Votre nom" name="name">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>E-mail</label>
                                    <input type="email" class="form_control" placeholder="Votre e-mail" name="email">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Arrivée</label>
                                    <input type="date" class="form_control" name="CheckIn">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Départ</label>
                                    <input type="date" class="form_control" name="CheckOut">
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <label>Message</label>
                                <div class="form-group">
                                    <textarea name="message" class="form_control" rows="4" placeholder="Écrivez votre message"></textarea>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="form-group">
                                    <button class="theme-btn style-one" type="button">VÉRIFIER LES DISPONIBILITÉS <i class="far fa-arrow-right"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="luxivo-blog_one pt-115 pb-80">
    <div class="container">
        <div class="row align-items-end">
            <div class="col-xl-6 col-lg-8">
                <div class="section-title mb-50 text-center text-lg-start">
                    <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                    <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">ACTUALITÉS</span>
                    <h2 data-aos="fade-up" data-aos-duration="1200">Découvrez nos derniers articles</h2>
                </div>
            </div>
            <div class="col-xl-6 col-lg-4">
                <div class="luxivo-button mb-60 text-center text-lg-end" data-aos="fade-up" data-aos-duration="800">
                    <a href="{{ route('blog.index') }}" class="theme-btn style-one">VOIR TOUS LES ARTICLES<i class="far fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            @foreach([
                ['img' => 'gallery-1.jpg', 'title' => "L'expérience de luxe : ce qui rend notre hôtel unique"],
                ['img' => 'about-1.jpg', 'title' => 'Découvrez le charme de notre expérience signature'],
                ['img' => 'choose-1.jpg', 'title' => "L'histoire d'Espace Katioka, où le luxe rencontre l'authenticité"],
            ] as $post)
            <div class="col-lg-4 col-md-6">
                <div class="luxivo-blog-post-item style-one mb-40">
                    <div class="post-thumbnail"><img src="{{ asset('assets/images/stock/'.$post['img']) }}" alt="{{ $post['title'] }}"></div>
                    <div class="post-content">
                        <div class="post-meta">
                            <span><i class="far fa-user"></i>Par <a href="#">Admin</a></span>
                            <span><i class="far fa-comments"></i><a href="#">(03) commentaires</a></span>
                        </div>
                        <h4><a href="{{ route('blog.show') }}">{{ $post['title'] }}</a></h4>
                        <a href="{{ route('blog.show') }}" class="theme-btn style-two">Lire la suite<i class="far fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="luxivo-instagram-sec pt-110 pb-120">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="text-box text-center mb-55">
                    <h5>Suivez-nous sur Instagram</h5>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="gallery-slider" data-aos="fade-up" data-aos-duration="1000">
            @foreach(['hero-bg.jpg','gallery-1.jpg','gallery-2.jpg','gallery-3.jpg','gallery-4.jpg','about-2.jpg','choose-1.jpg'] as $img)
            <div class="luxivo-gallery-item style-one">
                <div class="thumbnail">
                    <img src="{{ asset('assets/images/stock/'.$img) }}" alt="galerie">
                    <div class="hover-content">
                        <a href="https://www.instagram.com/" target="_blank" class="icon-btn"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

@endsection
