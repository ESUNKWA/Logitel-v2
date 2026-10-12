<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>@yield('title', 'Tableau de bord') — {{ $hotelInfo->name() }}</title>
        <link rel="shortcut icon" href="{{ asset('app/images/favicon.png') }}" type="image/png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gilda+Display&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ \App\Support\Asset::url('app/css/admin.css') }}">
        @include('admin.pwa._head')
        @stack('styles')
    </head>
    @php($simpleUi = (bool) auth()->user()?->usesSimpleUi())
    <body class="{{ $simpleUi ? 'ui-simple' : '' }}">
        <div class="admin-wrapper">
            {{-- "Mode complet": sidebar. "Mode simple": short top navigation + phone tab bar (_simple-nav). --}}
            @unless ($simpleUi)
            <aside class="admin-sidebar">
                <div class="admin-sidebar__brand">
                    <a href="{{ route('admin.dashboard') }}" data-initials="{{ $hotelInfo->initials() }}">{{ $hotelInfo->name() }}</a>
                    <span>Gestion hôtelière</span>
                </div>
                <nav class="admin-sidebar__nav">
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard', 'admin.stats') ? 'active' : '' }}">
                        <i class="far fa-tachometer-alt"></i> Tableau de bord
                    </a>
                    @can('rooms.view')
                        <a href="{{ route('admin.rooms.board') }}" class="{{ request()->routeIs('admin.rooms.board') ? 'active' : '' }}">
                            <i class="far fa-hotel"></i> Chambres
                        </a>
                    @endcan
                    @can('affectations.view')
                        <a href="{{ route('admin.stay-requests.index') }}" class="{{ request()->routeIs('admin.stay-requests.*') ? 'active' : '' }}">
                            <i class="far fa-mobile-alt"></i> Demandes clients
                            @php($pendingRequests = \App\Models\StayRequest::query()->holding()->count())
                            <span class="badge rounded-pill bg-warning text-dark ms-auto" data-request-count @if (! $pendingRequests) hidden @endif>{{ $pendingRequests }}</span>
                        </a>
                    @endcan
                    @can('affectations.view')
                        <a href="{{ route('admin.affectations.index') }}" class="{{ request()->routeIs('admin.affectations.*') ? 'active' : '' }}">
                            <i class="far fa-key"></i> Affectations
                        </a>
                    @endcan
                    @can('reservations.view')
                        <a href="{{ route('admin.reservations.index') }}" class="{{ request()->routeIs('admin.reservations.*') ? 'active' : '' }}">
                            <i class="far fa-calendar-alt"></i> Réservations
                        </a>
                        <a href="{{ route('admin.booking-requests.index') }}" class="{{ request()->routeIs('admin.booking-requests.*') ? 'active' : '' }}">
                            <i class="far fa-globe"></i> Demandes en ligne
                            @php($pendingBookings = \App\Models\BookingRequest::query()->pending()->count())
                            @if ($pendingBookings)
                                <span class="badge rounded-pill bg-warning text-dark ms-auto">{{ $pendingBookings }}</span>
                            @endif
                        </a>
                    @endcan
                    @can('guests.view')
                        <a href="{{ route('admin.guests.index') }}" class="{{ request()->routeIs('admin.guests.*') ? 'active' : '' }}">
                            <i class="far fa-users"></i> Clients
                        </a>
                    @endcan
                    @can('invoices.view')
                        <a href="{{ route('admin.invoices.index') }}" class="{{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}">
                            <i class="far fa-file-invoice-dollar"></i> Factures
                        </a>
                    @endcan
                    @can('payments.create')
                        <a href="{{ route('admin.revenue.index') }}" class="{{ request()->routeIs('admin.revenue.*') ? 'active' : '' }}">
                            <i class="far fa-cash-register"></i> Recettes
                        </a>
                    @endcan
                    @canany(['site.manage', 'site.stats'])
                        <div class="admin-sidebar__section">Site web</div>
                    @endcanany
                    @can('site.stats')
                        <a href="{{ route('admin.site.stats') }}" class="{{ request()->routeIs('admin.site.stats') ? 'active' : '' }}">
                            <i class="far fa-chart-bar"></i> Statistiques
                        </a>
                    @endcan
                    @can('site.manage')
                        <a href="{{ route('admin.site.home.edit') }}" class="{{ request()->routeIs('admin.site.home.*') ? 'active' : '' }}">
                            <i class="far fa-globe"></i> Accueil du site
                        </a>
                        <a href="{{ route('admin.site.services.index') }}" class="{{ request()->routeIs('admin.site.services.*') ? 'active' : '' }}">
                            <i class="far fa-concierge-bell"></i> Services
                        </a>
                        <a href="{{ route('admin.site.events.index') }}" class="{{ request()->routeIs('admin.site.events.*') ? 'active' : '' }}">
                            <i class="far fa-calendar-star"></i> Événements
                        </a>
                        <a href="{{ route('admin.site.gallery.index') }}" class="{{ request()->routeIs('admin.site.gallery.*') ? 'active' : '' }}">
                            <i class="far fa-images"></i> Galerie
                        </a>
                    @endcan
                    @can('rooms.manage')
                        <div class="admin-sidebar__section">Configuration</div>
                        <a href="{{ route('admin.room-types.index') }}" class="{{ request()->routeIs('admin.room-types.*') ? 'active' : '' }}">
                            <i class="far fa-layer-group"></i> Types de chambre
                        </a>
                    @endcan
                    @can('settings.manage')
                        @cannot('rooms.manage')
                            <div class="admin-sidebar__section">Configuration</div>
                        @endcannot
                        <a href="{{ route('admin.hotel.edit') }}" class="{{ request()->routeIs('admin.hotel.*') ? 'active' : '' }}">
                            <i class="far fa-building"></i> Hôtel
                        </a>
                        <a href="{{ route('admin.settings.edit') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                            <i class="far fa-sliders-h"></i> Paramètres
                        </a>
                    @endcan
                    @can('users.manage')
                        <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                            <i class="far fa-user-tie"></i> Personnel
                        </a>
                    @endcan
                    @can('roles.manage')
                        <a href="{{ route('admin.roles.index') }}" class="{{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                            <i class="far fa-user-shield"></i> Rôles
                        </a>
                    @endcan
                </nav>
            </aside>
            @endunless

            <div class="admin-main">
                <header class="admin-topbar">
                    @if ($simpleUi)
                        @include('admin.layouts._simple-nav')
                    @else
                        <button type="button" class="admin-topbar__toggle" id="sidebarToggle" aria-label="Afficher/masquer le menu">
                            <i class="far fa-bars"></i>
                        </button>
                    @endif
                    <div class="admin-topbar__spacer"></div>
                    {{-- Shown by admin.js when the device can install the application (or on iPhone: how to). --}}
                    <button type="button" class="btn btn-sm btn-outline-primary pwa-install" data-pwa-install hidden>
                        <i class="far fa-mobile-alt"></i><span class="pwa-install__label"> Installer l'application</span>
                    </button>
                    <form method="POST" action="{{ route('admin.ui-mode.update') }}" class="ui-mode-switch">
                        @csrf
                        <input type="hidden" name="mode" value="{{ $simpleUi ? 'complet' : 'simple' }}">
                        <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ $simpleUi ? 'Passer au mode complet (menu détaillé, tableau de bord chiffré)' : 'Passer au mode simple (accueil réception, navigation rapide)' }}">
                            <i class="far {{ $simpleUi ? 'fa-th-list' : 'fa-th-large' }}"></i><span class="ui-mode-switch__label"> {{ $simpleUi ? 'Mode complet' : 'Mode simple' }}</span>
                        </button>
                    </form>
                    <div class="admin-topbar__user">
                        <a href="{{ route('admin.profile.edit') }}" class="text-reset text-decoration-none" title="Mon profil">
                            <i class="far fa-user-circle"></i><span class="admin-topbar__username"> {{ auth()->user()?->name }}</span>
                        </a>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Déconnexion" aria-label="Déconnexion"><i class="far fa-sign-out-alt"></i><span class="admin-topbar__logout-label"> Déconnexion</span></button>
                        </form>
                    </div>
                </header>

                <main class="admin-content">
                    {{-- A page defining section "sweetalert" gets its messages and errors as SweetAlert pop-ups
                         (admin.js, #swalFlash) instead of the alert boxes below. --}}
                    @php($useSweetAlert = \Illuminate\Support\Facades\View::hasSection('sweetalert'))
                    @if ($useSweetAlert)
                        <script type="application/json" id="swalFlash">@json(['status' => session('status'), 'errors' => $errors->all()])</script>
                    @endif
                    @if (session('status') && ! $useSweetAlert)
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif
                    {{-- A view showing its errors inline (e.g. the assignment modal) defines section "inline-errors". --}}
                    @if ($errors->any() && ! $useSweetAlert && ! \Illuminate\Support\Facades\View::hasSection('inline-errors'))
                        <div class="alert alert-danger" data-page-errors>
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </main>

                @if ($simpleUi)
                    @include('admin.layouts._simple-tabbar')
                @endif
            </div>
        </div>

        @if (auth()->user()?->receivesCheckoutAlarm())
            {{-- End-of-stay alarm, filled by public/js/admin.js: rings continuously until check-out or
                 extension (Réception only — never the Gérant). --}}
            <div
                class="checkout-alerts"
                id="checkoutAlerts"
                data-feed-url="{{ route('admin.alerts.checkouts') }}"
                data-csrf="{{ csrf_token() }}"
                aria-live="assertive"
            ></div>
        @endif

        @if (session('receipt') && auth()->user()->can('invoices.view'))
            {{-- Right after an affectation / a réservation: receipt preview, printed from the frame (public/js/admin.js). --}}
            <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalTitle" aria-hidden="true" data-auto-open>
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable receipt-modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="receiptModalTitle"><i class="far fa-receipt me-2"></i>Reçu du client — aperçu</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body p-0">
                            <iframe src="{{ route('admin.receipts.show', ['reservation' => session('receipt'), 'apercu' => 1]) }}" title="Aperçu du reçu" class="receipt-frame" data-receipt-frame></iframe>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                            <button type="button" class="btn btn-primary" data-receipt-print><i class="fas fa-print"></i> Imprimer le reçu</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @can('affectations.manage')
            {{-- New guest requests (sent from their phone), filled by public/js/admin.js (polls every 15 s). --}}
            <div class="request-alerts" id="requestAlerts" data-feed-url="{{ route('admin.stay-requests.feed') }}" aria-live="polite"></div>
        @endcan

        <script src="{{ asset('vendor/bootstrap/popper.min.js') }}"></script>
        <script src="{{ asset('vendor/bootstrap/bootstrap.min.js') }}"></script>
        @if ($useSweetAlert ?? false)
            <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
        @endif
        <script src="{{ \App\Support\Asset::url('app/js/admin.js') }}"></script>
        <script src="{{ \App\Support\Asset::url('app/js/pwa.js') }}"></script>
        @stack('scripts')
    </body>
</html>
