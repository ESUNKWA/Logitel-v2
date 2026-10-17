{{-- Pricing mode as a coloured badge with its icon (colours: admin.css, « Pricing mode colours »).
     Usage: <x-admin::mode-badge :mode="$stay->pricing_mode" /> --}}
@props(['mode'])
<span {{ $attributes->merge(['class' => 'mode-badge mode--'.$mode->value]) }}><i class="fas {{ $mode->icon() }}" aria-hidden="true"></i> {{ $mode->getLabel() }}</span>
