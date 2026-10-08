<footer class="main-footer bg_cover" data-src="{{ asset('assets/images/stock/footer-bg.jpg') }}">
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
                                <x-brand-logo variant="white" />
                            </div>
                            <p>{{ $hotelInfo->get('tagline') ?: $hotelInfo->name().' vous accueille pour un séjour alliant confort, hospitalité et sérénité, au cœur de la Côte d\'Ivoire.' }}</p>
                            @if ($hotelInfo->socials())
                                <div class="social-box mb-20">
                                    @foreach ($hotelInfo->socials() as [$label, $url, $icon])
                                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $label }}"><i class="{{ $icon }}"></i></a>
                                    @endforeach
                                </div>
                            @endif
                            <div class="newsletter-box">
                                <form>
                                    <div class="form-group">
                                        <input type="email" placeholder="Votre adresse e-mail" class="form_control" required>
                                        <button class="theme-btn style-one" type="button">S'ABONNER <i class="far fa-paper-plane"></i></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5 col-sm-12 order-3 order-xl-2">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="footer-widget footer-nav-widget mb-40">
                                <div class="widget-content">
                                    <h4 class="widget-title">Liens rapides</h4>
                                    <ul class="widget-nav">
                                        <li><a href="{{ route('about') }}">À propos</a></li>
                                        <li><a href="{{ route('rooms.index') }}">Chambres & Suites</a></li>
                                        <li><a href="{{ route('services.index') }}">Services</a></li>
                                        <li><a href="{{ route('blog.index') }}">Actualités</a></li>
                                        <li><a href="{{ route('team.index') }}">Notre équipe</a></li>
                                        <li><a href="{{ route('contact') }}">Contact</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="footer-widget footer-nav-widget mb-40">
                                <div class="widget-content">
                                    <h4 class="widget-title">Liens utiles</h4>
                                    <ul class="widget-nav">
                                        <li><a href="{{ route('rooms.index') }}">Chambres & Suites</a></li>
                                        <li><a href="{{ route('offers.index') }}">Offres</a></li>
                                        <li><a href="{{ route('team.index') }}">Équipe</a></li>
                                        <li><a href="{{ route('gallery') }}">Galerie</a></li>
                                        <li><a href="{{ route('pricing') }}">Tarifs</a></li>
                                        <li><a href="{{ route('faq') }}">FAQ</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-sm-12 order-2 order-xl-3">
                    <div class="footer-widget footer-post-widget mb-40">
                        <div class="widget-content">
                            <h4 class="widget-title">Articles récents</h4>
                            <div class="recent-post-list">
                                <div class="footer-recent-post mb-30">
                                    <div class="thumbnail">
                                        <img src="{{ asset('assets/images/stock/gallery-3.jpg') }}" alt="Miniature article">
                                    </div>
                                    <div class="content">
                                        <span><i class="fal fa-calendar-alt"></i>08 août 2025</span>
                                        <h6><a href="{{ route('blog.show') }}">Ce qui rend notre hôtel unique</a></h6>
                                    </div>
                                </div>
                                <div class="footer-recent-post mb-30">
                                    <div class="thumbnail">
                                        <img src="{{ asset('assets/images/stock/gallery-4.jpg') }}" alt="Miniature article">
                                    </div>
                                    <div class="content">
                                        <span><i class="fal fa-calendar-alt"></i>08 août 2025</span>
                                        <h6><a href="{{ route('blog.show') }}">Découvrez notre expérience signature</a></h6>
                                    </div>
                                </div>
                            </div>
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
