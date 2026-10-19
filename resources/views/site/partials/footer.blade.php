<footer class="main-footer">
    <div class="container">
        <div class="footer-top-area">
            <div class="row">
                {{-- Hotel contact details: Configuration → Hôtel (empty ones are hidden). --}}
                @if ($hotelInfo->get('phone'))
                    <div class="col-lg-4 col-md-6">
                        <div class="luxivo-info-box style-one">
                            <div class="icon"><i class="fal fa-phone-volume"></i></div>
                            <div class="content">
                                <span>Appelez-nous</span>
                                <h5><a href="{{ $hotelInfo->telHref($hotelInfo->get('phone')) }}">{{ $hotelInfo->get('phone') }}</a></h5>
                            </div>
                        </div>
                    </div>
                @endif
                @if ($hotelInfo->get('email'))
                    <div class="col-lg-4 col-md-6">
                        <div class="luxivo-info-box style-one">
                            <div class="icon"><i class="fal fa-envelope"></i></div>
                            <div class="content">
                                <span>Envoyez-nous un e-mail</span>
                                <h5><a href="mailto:{{ $hotelInfo->get('email') }}">{{ $hotelInfo->get('email') }}</a></h5>
                            </div>
                        </div>
                    </div>
                @endif
                @if ($hotelInfo->fullAddress())
                    <div class="col-lg-4 col-md-6">
                        <div class="luxivo-info-box style-one">
                            <div class="icon"><i class="fal fa-map-marker-alt"></i></div>
                            <div class="content">
                                <span>Adresse</span>
                                <h5>{{ $hotelInfo->fullAddress() }}</h5>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="footer-widget-area pt-80 pb-40">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-4 col-md-6 col-sm-12 order-1">
                    <div class="footer-widget footer-about-widget mb-40">
                        <div class="widget-content">
                            <div class="footer-logo mb-30">
                                <x-site::brand-logo variant="white" />
                            </div>
                            <p>{{ $hotelInfo->get('tagline') ?: $hotelInfo->name().' vous accueille pour un séjour alliant confort, hospitalité et sérénité, au cœur de la Côte d\'Ivoire.' }}</p>
                            @if ($hotelInfo->socials())
                                <div class="social-box mb-20">
                                    @foreach ($hotelInfo->socials() as [$label, $url, $icon])
                                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $label }}"><i class="{{ $icon }}"></i></a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 col-sm-12 order-3 order-xl-2">
                    <div class="footer-widget footer-nav-widget mb-40">
                        <div class="widget-content">
                            <h4 class="widget-title">Liens rapides</h4>
                            <ul class="widget-nav">
                                <li><a href="{{ route('home') }}">Accueil</a></li>
                                <li><a href="{{ route('rooms.index') }}">Chambres</a></li>
                                <li><a href="{{ route('services.index') }}">Services</a></li>
                                <li><a href="{{ route('gallery') }}">Galerie</a></li>
                                <li><a href="{{ route('contact') }}">Contact</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 col-sm-12 order-2 order-xl-3">
                    <div class="footer-widget footer-nav-widget mb-40">
                        <div class="widget-content">
                            <h4 class="widget-title">Une question ?</h4>
                            <p>Notre réception vous renseigne sur nos chambres, nos tarifs et nos disponibilités.</p>
                            <a href="{{ route('contact') }}" class="theme-btn style-one mt-2">Nous contacter<i class="far fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="copyright-area">
        <div class="container">
            <div class="row">
                <div class="col-xl-12">
                    <div class="copyright-text text-center">
                        <p>&copy; {{ date('Y') }} {{ $hotelInfo->name() }}. Tous droits réservés.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
