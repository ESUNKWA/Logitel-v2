@php
    $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $paid = (float) $invoice->amountPaid();
    $remaining = (float) $invoice->remainingAmount();
    $cancelled = $reservation->status === \App\Enums\ReservationStatus::Annulee;
    $receipt = config('hotel.receipt');
    $at = fn ($d) => $d?->format('d/m/Y H\hi');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Reçu {{ $invoice->number }} — {{ $hotelInfo->name() }}</title>
    {{-- No page size here on purpose: the printer's own paper (set in its driver / the print
         dialog) is the page, the ticket starts at its top. A CSS size different from that paper
         makes Chrome scale or centre the ticket on it (blank space above it). --}}
    <style>
        /* Thermal ticket: one column, black on white, the roll's width. */
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            background: #e9edf3;
            color: #000;
            font-family: 'Helvetica Neue', Arial, 'Liberation Sans', sans-serif;
            font-size: {{ $width === 58 ? '10.5px' : '12px' }};
            line-height: 1.3;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body.is-embedded { background: #e9edf3; }
        .toolbar {
            position: sticky; top: 0; z-index: 2;
            display: flex; gap: 8px; justify-content: center; align-items: center;
            padding: 10px; background: #15264a; color: #fff;
            font-size: 14px;
        }
        .toolbar button {
            font: inherit; font-weight: 600; border: 0; border-radius: 8px; padding: 8px 16px; cursor: pointer;
        }
        .toolbar .print { background: #c9a24b; color: #15264a; }
        .toolbar .close { background: rgba(255, 255, 255, .15); color: #fff; }
        .toolbar small { opacity: .75; margin-left: 6px; }
        .sheet { padding: 18px 0 30px; }
        .ticket {
            width: {{ $width }}mm;
            margin: 0 auto;
            padding: 2mm {{ $width === 58 ? '2.5mm' : '4mm' }} 4mm;
            background: #fff;
            box-shadow: 0 4px 18px rgba(15, 27, 45, .18);
        }
        .center { text-align: center; }
        .name { font-size: 1.35em; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; }
        .muted { font-size: .92em; }
        .sep { border: 0; border-top: 1px dashed #000; margin: 1.6mm 0; }
        .row { display: flex; justify-content: space-between; gap: 2mm; }
        .row > span:first-child { flex-shrink: 0; }
        .row > span:last-child { text-align: right; overflow-wrap: anywhere; }
        .sub { padding-left: 3mm; font-size: .92em; }
        .total { font-size: 1.25em; font-weight: 800; }
        .strong { font-weight: 700; }
        .stamp { border: 2px solid #000; text-align: center; font-weight: 800; font-size: 1.2em; letter-spacing: 2px; padding: 1mm; margin: 2mm 0; }
        @media print {
            body, body.is-embedded { background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; }
            .ticket { box-shadow: none; margin: 0; width: 100%; max-width: {{ $width }}mm; }
        }
    </style>
</head>
<body class="{{ $embedded ? 'is-embedded' : '' }}">
    @unless ($embedded)
        <div class="toolbar">
            <button type="button" class="print" onclick="window.print()">🖨 Imprimer</button>
            <button type="button" class="close" onclick="if (history.length > 1) { history.back(); } else { window.close(); }">Fermer</button>
            <small>Aperçu — rouleau {{ $width }} mm</small>
        </div>
    @endunless

    <div class="sheet">
        <div class="ticket">
            <div class="center">
                <div class="name">{{ $hotelInfo->name() }}</div>
                @if ($hotelInfo->fullAddress())<div class="muted">{{ $hotelInfo->fullAddress() }}</div>@endif
                @if ($hotelInfo->phones())<div class="muted">Tél. {{ implode(' / ', $hotelInfo->phones()) }}</div>@endif
                @if ($hotelInfo->websiteLabel())<div class="muted">{{ $hotelInfo->websiteLabel() }}</div>@endif
                @if ($hotelInfo->legalLine())<div class="muted">{{ $hotelInfo->legalLine() }}</div>@endif
            </div>

            <hr class="sep">

            <div class="row"><span>Reçu N°</span><span class="strong">{{ $invoice->number }}</span></div>
            <div class="row"><span>Date</span><span>{{ $at($invoice->payments->last()?->paid_at ?? $reservation->created_at) }}</span></div>

            <hr class="sep">
            <div class="row"><span>Client</span><span class="strong">{{ $reservation->guest?->fullName() }}</span></div>
            @if ($reservation->guest?->isCompany())
                @if ($reservation->guest->tax_id)
                    <div class="row"><span>RCCM / NCC</span><span>{{ $reservation->guest->tax_id }}</span></div>
                @endif
                @if ($reservation->guest->contactName())
                    <div class="row"><span>Contact</span><span>{{ $reservation->guest->contactName() }}</span></div>
                @endif
            @endif
            @if ($reservation->guest?->phone)
                <div class="row"><span>Tél.</span><span>{{ $reservation->guest->phone }}</span></div>
            @endif

            <hr class="sep">
            <div class="row"><span>Chambre</span><span class="strong">N°{{ $reservation->room?->number }}</span></div>
            <div class="row"><span>Type</span><span>{{ $reservation->room?->roomType?->name }}</span></div>
            <div class="row"><span>Formule</span><span>{{ $reservation->pricing_mode->getLabel() }}</span></div>
            <div class="row"><span>{{ $isStay ? 'Entrée' : 'Arrivée prévue' }}</span><span>{{ $at($reservation->check_in) }}</span></div>
            <div class="row"><span>{{ $isStay ? 'Sortie prévue' : 'Départ prévu' }}</span><span>{{ $at($reservation->check_out) }}</span></div>
            <div class="row"><span>Personnes</span><span>{{ $reservation->adults }} ad.{{ $reservation->children ? ' + '.$reservation->children.' enf.' : '' }}</span></div>

            <hr class="sep">
            @if ($reservation->hasSpecialRate())
                <div class="row"><span>Forfait {{ $reservation->pricing_mode->durationLabel($reservation->duration) }}</span><span>{{ $fcfa($reservation->special_price) }}</span></div>
                <div class="row sub"><span>au lieu de {{ $fcfa($reservation->normalSubtotal()) }} ({{ $reservation->special_rate_name }})</span><span></span></div>
            @else
                <div class="row"><span>{{ $reservation->pricing_mode->durationLabel($reservation->duration) }} × {{ $fcfa($reservation->unit_rate) }}</span><span></span></div>
            @endif
            @if ($reservation->hasDiscount())
                <div class="row"><span>Sous-total</span><span>{{ $fcfa($reservation->subtotal()) }}</span></div>
                <div class="row"><span>Remise {{ $reservation->discount_type === \App\Models\Reservation::DISCOUNT_PERCENT ? '('.$reservation->discountLabel(false).')' : '' }}</span><span>− {{ $fcfa($reservation->discount_amount) }}</span></div>
                @if ($reservation->discount_reason)
                    <div class="row sub"><span>{{ $reservation->discount_reason }}</span><span></span></div>
                @endif
            @endif
            <div class="row total"><span>TOTAL</span><span>{{ $fcfa($invoice->amount) }} FCFA</span></div>

            @if ($invoice->payments->isNotEmpty())
                <hr class="sep">
                <div class="strong">Paiements</div>
                @foreach ($invoice->payments as $payment)
                    <div class="row"><span>{{ $payment->method->getLabel() }} <span class="muted">{{ $payment->paid_at?->format('d/m H\hi') }}</span></span><span>{{ $fcfa($payment->amount) }}</span></div>
                    @if ($payment->amount_received !== null && (float) $payment->change_given > 0)
                        <div class="row sub"><span>Reçu</span><span>{{ $fcfa($payment->amount_received) }}</span></div>
                        <div class="row sub"><span>Monnaie rendue</span><span>{{ $fcfa($payment->change_given) }}</span></div>
                    @endif
                @endforeach
                <div class="row strong"><span>Total payé</span><span>{{ $fcfa($paid) }} FCFA</span></div>
            @endif

            @if ($cancelled)
                <div class="stamp">ANNULÉ</div>
            @elseif ($remaining <= 0)
                <div class="stamp">SOLDÉ</div>
            @else
                <div class="row total"><span>{{ $isStay ? 'RESTE DÛ' : 'À PAYER À L\'ARRIVÉE' }}</span><span>{{ $fcfa($remaining) }} FCFA</span></div>
            @endif

            <hr class="sep">
            @if ($receipt['footer'])
                <div class="center strong">{{ $receipt['footer'] }}</div>
            @endif
            <div class="center muted" style="margin-top: 1.5mm;">Imprimé le {{ now()->format('d/m/Y à H\hi') }}</div>
        </div>
    </div>
</body>
</html>
