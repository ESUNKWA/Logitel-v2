{{-- Entry date and time of an affectation (client request, 2026-10-19), the line right above
     « Départ prévu » in the « Règlement » box. Display only — the stay always starts at the
     server's time when the form is saved (RoomAssignmentRequest::arrival()); admin.js
     ([data-entry-time]) keeps it on the current minute. --}}
<div class="assign-pay__entry">Entrée : <span data-entry-time>{{ now()->format('d/m/Y') }} à {{ now()->format('H\hi') }}</span></div>
