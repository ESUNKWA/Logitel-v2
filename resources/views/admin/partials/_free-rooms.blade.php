{{-- « Chambres disponibles » on the receptionists' home (FrontDeskController::freeRooms()):
     free rooms by type with the type's cheapest formula; a room opens the assignment modal on the board. --}}
@php
    $total = $freeRooms->sum(fn ($rooms) => $rooms->count());
    $canAssign = auth()->user()->can('affectations.manage');
@endphp
<section class="home-free" aria-label="Chambres disponibles">
    <div class="home-free__head">
        <h2 class="home-free__title"><i class="fas fa-door-open"></i> Chambres disponibles</h2>
        <span class="home-free__count">{{ $total }} libre{{ $total > 1 ? 's' : '' }}</span>
        <a href="{{ route('admin.rooms.board') }}#disponible" class="home-free__all">Voir le tableau</a>
    </div>
    @forelse ($freeRooms as $typeName => $rooms)
        @php($lowest = collect(\App\Enums\PricingMode::cases())->map(fn ($m) => [$m, $m->rateFor($rooms->first()->roomType)])->filter(fn ($p) => $p[1] !== null)->sortBy(fn ($p) => (float) $p[1])->first())
        <div class="home-free__type">
            <div class="home-free__type-name">
                {{ $typeName }}
                @if ($lowest)
                    <span class="home-free__rate">dès {{ number_format((float) $lowest[1], 0, ',', ' ') }} F {{ $lowest[0]->rateUnit() }}</span>
                @endif
            </div>
            <div class="home-free__rooms">
                @foreach ($rooms as $room)
                    <a href="{{ route('admin.rooms.board', $canAssign ? ['affecter' => $room->id] : []) }}#disponible" class="home-free__room"
                        @if ($canAssign) title="Affecter la chambre N°{{ $room->number }}" @endif>N°{{ $room->number }}</a>
                @endforeach
            </div>
        </div>
    @empty
        <p class="home-free__empty">Aucune chambre libre pour le moment.</p>
    @endforelse
</section>
