{{-- "Mode simple" on phones: the daily screens as a bottom tab bar (hidden on wider screens). --}}
<nav class="simple-tabbar" aria-label="Navigation rapide">
    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard', 'admin.front-desk.*') ? 'active' : '' }}"><i class="far fa-home"></i><span>Accueil</span></a>
    @can('rooms.view')
        <a href="{{ route('admin.rooms.board') }}" class="{{ request()->routeIs('admin.rooms.*') ? 'active' : '' }}"><i class="far fa-hotel"></i><span>Chambres</span></a>
    @endcan
    @can('affectations.view')
        <a href="{{ route('admin.affectations.index') }}" class="{{ request()->routeIs('admin.affectations.*') ? 'active' : '' }}"><i class="far fa-key"></i><span>Séjours</span></a>
    @endcan
    @can('reservations.view')
        <a href="{{ route('admin.reservations.index') }}" class="{{ request()->routeIs('admin.reservations.*') ? 'active' : '' }}"><i class="far fa-calendar-alt"></i><span>Réserv.</span></a>
    @endcan
    @can('guests.view')
        <a href="{{ route('admin.guests.index') }}" class="{{ request()->routeIs('admin.guests.*') ? 'active' : '' }}"><i class="far fa-users"></i><span>Clients</span></a>
    @endcan
</nav>
