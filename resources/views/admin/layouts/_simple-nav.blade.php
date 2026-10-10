{{--
    "Mode simple" top navigation: brand, the daily screens, the guest-request counter and a
    "Plus" menu (invoices, figures, configuration). On phones the daily screens move to the
    bottom tab bar (_simple-tabbar) and only the brand, counter and "Plus" stay here.
--}}
@php
    $isActive = fn (string ...$patterns) => request()->routeIs(...$patterns) ? 'active' : '';
    $pendingRequests = auth()->user()->can('affectations.view') ? \App\Models\StayRequest::query()->holding()->count() : null;
    $canConfigure = auth()->user()->canAny(['rooms.manage', 'settings.manage', 'users.manage', 'roles.manage']);
@endphp

<a href="{{ route('admin.dashboard') }}" class="simple-nav__brand" data-initials="{{ $hotelInfo->initials() }}">
    <span class="simple-nav__brand-name">{{ $hotelInfo->name() }}</span>
</a>

<nav class="simple-nav" aria-label="Navigation principale">
    <a href="{{ route('admin.dashboard') }}" class="{{ $isActive('admin.dashboard', 'admin.front-desk.*') }}"><i class="far fa-home"></i> Accueil</a>
    @can('rooms.view')
        <a href="{{ route('admin.rooms.board') }}" class="{{ $isActive('admin.rooms.*') }}"><i class="far fa-hotel"></i> Chambres</a>
    @endcan
    @can('affectations.view')
        <a href="{{ route('admin.affectations.index') }}" class="{{ $isActive('admin.affectations.*') }}"><i class="far fa-key"></i> Affectations</a>
    @endcan
    @can('reservations.view')
        <a href="{{ route('admin.reservations.index') }}" class="{{ $isActive('admin.reservations.*') }}"><i class="far fa-calendar-alt"></i> Réservations</a>
    @endcan
    @can('guests.view')
        <a href="{{ route('admin.guests.index') }}" class="{{ $isActive('admin.guests.*') }}"><i class="far fa-users"></i> Clients</a>
    @endcan
</nav>

@if ($pendingRequests !== null)
    <a href="{{ route('admin.stay-requests.index') }}" class="simple-nav__icon {{ $isActive('admin.stay-requests.*') }}" title="Demandes clients" aria-label="Demandes clients">
        <i class="far fa-mobile-alt"></i>
        <span class="badge rounded-pill bg-warning text-dark" data-request-count @if (! $pendingRequests) hidden @endif>{{ $pendingRequests }}</span>
    </a>
@endif

<div class="dropdown">
    <button class="simple-nav__icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Plus">
        <i class="far fa-ellipsis-h"></i><span class="simple-nav__more-label"> Plus</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end simple-nav__menu">
        @can('affectations.view')
            <li><a class="dropdown-item" href="{{ route('admin.stay-requests.index') }}"><i class="far fa-mobile-alt"></i> Demandes clients</a></li>
            <li><a class="dropdown-item" href="{{ route('admin.stay-requests.qr') }}"><i class="far fa-qrcode"></i> QR code client</a></li>
        @endcan
        @can('invoices.view')
            <li><a class="dropdown-item" href="{{ route('admin.invoices.index') }}"><i class="far fa-file-invoice-dollar"></i> Factures</a></li>
            <li><a class="dropdown-item" href="{{ route('admin.front-desk.collect') }}"><i class="far fa-coins"></i> À encaisser</a></li>
        @endcan
        <li><a class="dropdown-item" href="{{ route('admin.stats') }}"><i class="far fa-chart-bar"></i> Tableau de bord chiffré</a></li>
        @can('site.manage')
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header"><i class="far fa-globe"></i> Site web</h6></li>
            <li><a class="dropdown-item" href="{{ route('admin.site.home.edit') }}"><i class="far fa-home"></i> Accueil du site</a></li>
            <li><a class="dropdown-item" href="{{ route('admin.site.services.index') }}"><i class="far fa-concierge-bell"></i> Services</a></li>
            <li><a class="dropdown-item" href="{{ route('admin.site.gallery.index') }}"><i class="far fa-images"></i> Galerie</a></li>
        @endcan
        @if ($canConfigure)
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header"><i class="far fa-cog"></i> Configuration</h6></li>
            @can('settings.manage')
                <li><a class="dropdown-item" href="{{ route('admin.hotel.edit') }}"><i class="far fa-building"></i> Hôtel</a></li>
            @endcan
            @can('rooms.manage')
                <li><a class="dropdown-item" href="{{ route('admin.room-types.index') }}"><i class="far fa-layer-group"></i> Types de chambre</a></li>
            @endcan
            @can('settings.manage')
                <li><a class="dropdown-item" href="{{ route('admin.settings.edit') }}"><i class="far fa-sliders-h"></i> Paramètres</a></li>
            @endcan
            @can('users.manage')
                <li><a class="dropdown-item" href="{{ route('admin.users.index') }}"><i class="far fa-user-tie"></i> Personnel</a></li>
            @endcan
            @can('roles.manage')
                <li><a class="dropdown-item" href="{{ route('admin.roles.index') }}"><i class="far fa-user-shield"></i> Rôles</a></li>
            @endcan
        @endif
    </ul>
</div>
