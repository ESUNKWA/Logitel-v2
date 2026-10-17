{{-- "Mode simple" on phones: the daily screens as a bottom tab bar (hidden on wider screens).
     « Clients » isn't here (client request, 2026-10-17): on phones it's in the « Plus » menu. --}}
<nav class="simple-tabbar" aria-label="Navigation rapide">
    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard', 'admin.front-desk.*') ? 'active' : '' }}"><i class="far fa-home"></i><span>Accueil</span></a>
    @can('rooms.view')
        <a href="{{ route('admin.rooms.board') }}" class="{{ request()->routeIs('admin.rooms.*') ? 'active' : '' }}"><i class="far fa-hotel"></i><span>Chambres</span></a>
    @endcan
    @can('affectations.view')
        <a href="{{ route('admin.affectations.index') }}" class="{{ request()->routeIs('admin.affectations.*') ? 'active' : '' }}"><i class="far fa-key"></i><span>Affect.</span></a>
    @endcan
    @can('reservations.view')
        <a href="{{ route('admin.reservations.index') }}" class="{{ request()->routeIs('admin.reservations.*') ? 'active' : '' }}"><i class="far fa-calendar-alt"></i><span>Réserv.</span></a>
    @endcan
</nav>
