<header class="header-area header-one {{ request()->routeIs('home') ? '' : 'page-header' }} transparent-header">
    <div class="header-navigation">
        <div class="container-fluid">
            <div class="primary-menu">
                <div class="site-branding">
                    <a href="{{ route('home') }}" class="brand-logo"><x-brand-logo variant="white" /></a>
                </div>
                <div class="theme-nav-menu">
                    <div class="theme-menu-top d-block d-xl-none">
                        <div class="site-branding">
                            <a href="{{ route('home') }}" class="brand-logo"><x-brand-logo variant="dark" /></a>
                        </div>
                    </div>
                    <nav class="main-menu">
                        <ul>
                            <li class="menu-item"><a href="{{ route('home') }}">Accueil</a></li>
                            <li class="menu-item has-children"><a href="{{ route('rooms.index') }}">Chambres & Suites</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('rooms.index') }}">Nos chambres</a></li>
                                    <li><a href="{{ route('rooms.show') }}">Détails d'une chambre</a></li>
                                </ul>
                            </li>
                            <li class="menu-item has-children"><a href="#">Pages</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('about') }}">À propos</a></li>
                                    <li><a href="{{ route('services.index') }}">Services</a>
                                        <ul class="sub-menu">
                                            <li><a href="{{ route('services.index') }}">Services</a></li>
                                            <li><a href="{{ route('services.show') }}">Détails d'un service</a></li>
                                        </ul>
                                    </li>
                                    <li><a href="{{ route('offers.index') }}">Offres</a></li>
                                    <li><a href="{{ route('offers.show') }}">Détails d'une offre</a></li>
                                    <li><a href="{{ route('gallery') }}">Notre galerie</a></li>
                                    <li><a href="{{ route('team.index') }}">Notre équipe</a></li>
                                    <li><a href="{{ route('team.show') }}">Détails d'un membre</a></li>
                                    <li><a href="{{ route('pricing') }}">Tarifs</a></li>
                                    <li><a href="{{ route('faq') }}">FAQ</a></li>
                                </ul>
                            </li>
                            <li class="menu-item has-children"><a href="#">Actualités</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('blog.index') }}">Blog</a></li>
                                    <li><a href="{{ route('blog.show') }}">Détails d'un article</a></li>
                                </ul>
                            </li>
                            <li class="menu-item"><a href="{{ route('contact') }}">Contact</a></li>
                        </ul>
                    </nav>
                    <div class="theme-nav-button mt-20 d-block d-md-none">
                        <a href="{{ route('contact') }}" class="theme-btn style-one">Nous contacter<i class="far fa-arrow-right"></i></a>
                    </div>
                    @if ($hotelInfo->socials())
                        <div class="theme-menu-bottom mt-50 d-block d-xl-none">
                            <h5>Suivez-nous</h5>
                            <ul class="social-link">
                                @foreach ($hotelInfo->socials() as [$label, $url, $icon])
                                    <li><a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $label }}"><i class="{{ $icon }}"></i></a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="nav-right-item">
                    <div class="nav-button d-none d-md-block">
                        <a href="{{ route('contact') }}" class="theme-btn style-one">Nous contacter<i class="far fa-arrow-right"></i></a>
                    </div>
                    <div class="navbar-toggler">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
