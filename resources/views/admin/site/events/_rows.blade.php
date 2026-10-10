@foreach ($events as $event)
    <tr class="{{ $event->isOver() ? 'text-muted' : '' }}">
        <td>
            @if ($event->photoUrl())
                <img src="{{ $event->photoUrl() }}" alt="" class="service-thumb">
            @else
                <span class="service-icon-thumb"><i class="far fa-calendar-star"></i></span>
            @endif
        </td>
        <td><strong>{{ $event->title }}</strong>@if ($event->location)<div class="small text-muted">{{ $event->location }}</div>@endif</td>
        <td>{{ $event->whenLabel() }}</td>
        <td>
            @if ($event->isOver())
                <span class="badge bg-secondary">Passé</span>
            @elseif (! $event->is_published)
                <span class="badge bg-secondary">Masqué</span>
            @elseif ($event->isOngoing())
                <span class="badge bg-success">En cours · en ligne</span>
            @else
                <span class="badge bg-success">En ligne</span>
            @endif
        </td>
        <td class="text-end text-nowrap">
            <a href="{{ route('admin.site.events.edit', $event) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
            <form method="POST" action="{{ route('admin.site.events.destroy', $event) }}" class="d-inline" onsubmit="return confirm('Supprimer cet événement ?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
            </form>
        </td>
    </tr>
@endforeach
