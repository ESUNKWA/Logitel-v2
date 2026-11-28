{{-- Days a restaurant dish is served (menu by day, client request 2026-11-28): seven toggles posted
     as days[]; none ticked = every day. $idPrefix: unique per form; $selected: ISO days; $form: the
     line's form id (catalogue grid) or null. --}}
<div class="menu-days" role="group" aria-label="Jours de service">
    @foreach (\App\Models\CatalogItem::WEEKDAYS as $number => [$short, $long])
        <input type="checkbox" class="btn-check" id="{{ $idPrefix }}-{{ $number }}" name="days[]" value="{{ $number }}" autocomplete="off"
            @if ($form ?? null) form="{{ $form }}" @endif @checked(in_array($number, $selected, true))>
        <label class="btn btn-sm menu-days__day" for="{{ $idPrefix }}-{{ $number }}" title="{{ ucfirst($long) }}">{{ $short }}</label>
    @endforeach
</div>
