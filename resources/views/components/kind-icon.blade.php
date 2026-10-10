{{-- Page-title icon: <x-kind-icon kind="affectation" /> (orange key) or kind="reservation" (blue calendar). --}}
@props(['kind'])
<span class="kind-icon kind-icon--{{ $kind }}" aria-hidden="true"><i class="fas {{ $kind === 'affectation' ? 'fa-key' : 'fa-calendar-alt' }}"></i></span>
