{{--
    Affectation vs réservation, shown the same way everywhere (both live in `reservations`):
    orange + key for an affectation, blue + calendar for a réservation, then the reference.
    <x-file-badge :file="$reservation" />  ·  :reference="false" for the badge alone  ·  :link="true" to link the reference
--}}
@props(['file', 'reference' => true, 'link' => false])
@if ($file)
    <span {{ $attributes->merge(['class' => 'file-kind']) }}>
        <span class="file-badge file-badge--{{ $file->kind() }}"><i class="fas {{ $file->isStay() ? 'fa-key' : 'fa-calendar-alt' }}" aria-hidden="true"></i> {{ $file->kindLabel() }}</span>
        @if ($reference)
            @if ($link)
                <a href="{{ $file->fileUrl() }}" class="file-ref">{{ $file->reference() }}</a>
            @else
                <span class="file-ref">{{ $file->reference() }}</span>
            @endif
        @endif
    </span>
@endif
