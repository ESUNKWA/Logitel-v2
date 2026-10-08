@props(['variant' => 'white'])

<span class="brand-logo-text is-{{ $variant }}">
    <span>{{ $hotelInfo->nameParts()[0] }}</span>
    @if ($hotelInfo->nameParts()[1] !== '')
        <span class="brand-logo-text__accent">{{ $hotelInfo->nameParts()[1] }}</span>
    @endif
</span>
