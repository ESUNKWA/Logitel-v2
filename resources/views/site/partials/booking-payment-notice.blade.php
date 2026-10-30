{{-- Online booking requests (client request, 2026-10-30): the réservation only becomes effective
     once paid by Mobile Money — Wave or Orange Money. Shown above « Envoyer ma demande » and on
     the follow-up page. $variant: 'dark' (form summary) or 'light' (follow-up page). --}}
<div class="site-booking__pay site-booking__pay--{{ $variant ?? 'light' }}" role="note">
    <div class="site-booking__pay-title"><i class="far fa-mobile-alt" aria-hidden="true"></i> Paiement par Mobile Money</div>
    <p>Votre réservation ne sera <strong>effective qu'après votre paiement par Mobile Money</strong> :</p>
    <ul class="site-booking__pay-methods">
        <li>Wave</li>
        <li>Orange Money</li>
    </ul>
    <p class="site-booking__pay-hint">La réception vous indique comment payer lorsqu'elle vous rappelle.</p>
</div>
