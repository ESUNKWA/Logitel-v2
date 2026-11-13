{{-- Pricing mode as a coloured badge with its icon (colours: admin.css, « Pricing mode colours »).
     Usage: <x-admin::mode-badge :mode="$stay->pricing_mode" :until="$stay->stay_until" />
     (`until` = the file's fixed séjour departure « 12:00 », else « Séjour (24 h) »). --}}
@props(['mode', 'until' => null])
<span {{ $attributes->merge(['class' => 'mode-badge mode--'.$mode->value]) }}><i class="fas {{ $mode->icon() }}" aria-hidden="true"></i> {{ $mode->getLabel($until) }}</span>
