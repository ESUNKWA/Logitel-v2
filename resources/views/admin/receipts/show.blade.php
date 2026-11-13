@php
    $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $paid = (float) $invoice->amountPaid();
    // What the guest handed over (cash: amount_received; Orange Money / Wave: the amount itself)
    // and the change given back — so received − change = paid.
    $received = (float) $invoice->payments->sum(fn ($p) => $p->amount_received ?? $p->amount);
    $change = (float) $invoice->payments->sum(fn ($p) => $p->change_given ?? 0);
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
        .name { font-size: 1.2em; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; }
        .muted { font-size: .92em; }
        /* Phone and website: printed larger so guests can read them (client request, 2026-11-09). */
        .contact { font-size: 1.2em; font-weight: 700; overflow-wrap: anywhere; }
        .contact--web { font-size: 1.1em; }
        .sep { border: 0; border-top: 1px dashed #000; margin: 1.6mm 0; }
        .row { display: flex; justify-content: space-between; gap: 2mm; }
        .row > span:first-child { flex-shrink: 0; }
        .row > span:last-child { text-align: right; overflow-wrap: anywhere; }
        .sub { padding-left: 3mm; font-size: .92em; }
        .perk { padding-left: 1mm; overflow-wrap: anywhere; }
        .total { font-size: 1.25em; font-weight: 800; }
        .strong { font-weight: 700; }
        /* Kept on one line (client request, 2026-11-09): sized to fit the roll's printable width. */
        .no-refund { margin-top: 1mm; white-space: nowrap; font-size: {{ $width === 58 ? '.78em' : '.93em' }}; }
        .doc-title { text-align: center; font-weight: 800; font-size: 1.25em; letter-spacing: 2px; margin-bottom: 1.2mm; }
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
                @if ($hotelInfo->phones())<div class="contact">Tél. {{ implode(' / ', array_map([\App\Support\PhoneFormat::class, 'display'], $hotelInfo->phones())) }}</div>@endif
                @if ($hotelInfo->websiteLabel())<div class="contact contact--web">{{ $hotelInfo->websiteLabel() }}</div>@endif
                @if ($hotelInfo->legalLine())<div class="muted">{{ $hotelInfo->legalLine() }}</div>@endif
            </div>

            <hr class="sep">

            {{-- A booking whose guest hasn't arrived yet (client request, 2026-11-10): « RÉSERVATION » on top
                 and the client's phone number. Affectation tickets stay without title nor client part. --}}
            @unless ($isStay)
                <div class="doc-title">RÉSERVATION</div>
            @endunless
            <div class="row"><span>Reçu N°</span><span class="strong">{{ $invoice->number }}</span></div>
            <div class="row"><span>Date</span><span>{{ $at($invoice->payments->last()?->paid_at ?? $reservation->created_at) }}</span></div>
            @if (! $isStay && $reservation->guest?->phone)
                <div class="row"><span>Tél. client</span><span class="strong">{{ \App\Support\PhoneFormat::display($reservation->guest->phone) }}</span></div>
            @endif

            <hr class="sep">
            {{-- No client part (name, phone, RCCM / contact), room type nor number of guests on the
                 ticket (client request, 2026-10-19) — except a booking's client phone, above (2026-11-10). --}}
            <div class="row"><span>Chambre</span><span class="strong">N°{{ $reservation->room?->number }}</span></div>
            <div class="row"><span>Formule</span><span>{{ $reservation->modeLabel() }}</span></div>
            <div class="row"><span>{{ $isStay ? 'Entrée' : 'Arrivée prévue' }}</span><span>{{ $at($reservation->check_in) }}</span></div>
            <div class="row"><span>{{ $isStay ? 'Sortie prévue' : 'Départ prévu' }}</span><span>{{ $at($reservation->check_out) }}</span></div>

            <hr class="sep">
            @if ($reservation->hasSpecialRate())
                <div class="row"><span>Forfait {{ $reservation->pricing_mode->durationLabel($reservation->duration, $reservation->stay_until) }}</span><span>{{ $fcfa($reservation->special_price) }}</span></div>
                <div class="row sub"><span>au lieu de {{ $fcfa($reservation->normalSubtotal()) }} ({{ $reservation->special_rate_name }})</span><span></span></div>
            @else
                <div class="row"><span>{{ $reservation->pricing_mode->durationLabel($reservation->duration, $reservation->stay_until) }} × {{ $fcfa($reservation->unit_rate) }}</span><span></span></div>
            @endif
            @if ($reservation->hasDiscount())
                <div class="row"><span>Sous-total</span><span>{{ $fcfa($reservation->subtotal()) }}</span></div>
                <div class="row"><span>Remise {{ $reservation->discount_type === \App\Models\Reservation::DISCOUNT_PERCENT ? '('.$reservation->discountLabel(false).')' : '' }}</span><span>− {{ $fcfa($reservation->discount_amount) }}</span></div>
                @if ($reservation->discount_reason)
                    <div class="row sub"><span>{{ $reservation->discount_reason }}</span><span></span></div>
                @endif
            @endif
            <div class="row total"><span>TOTAL</span><span>{{ $fcfa($invoice->amount) }} FCFA</span></div>

            {{-- Payment part: amount received from the guest, change given back (client request,
                 2026-10-25) and amount paid — still no method or time (2026-10-19; they stay on
                 the invoice page). --}}
            @if ($invoice->payments->isNotEmpty())
                <hr class="sep">
                <div class="row"><span>Montant reçu</span><span>{{ $fcfa($received) }} FCFA</span></div>
                <div class="row"><span>Monnaie rendue</span><span>{{ $fcfa($change) }} FCFA</span></div>
                <div class="row strong"><span>Montant payé</span><span>{{ $fcfa($paid) }} FCFA</span></div>
            @endif

            @if ($cancelled)
                <div class="stamp">ANNULÉ</div>
            @elseif ($remaining <= 0)
                <div class="stamp">SOLDÉ</div>
            @else
                <div class="row total"><span>{{ $isStay ? 'RESTE DÛ' : 'À PAYER À L\'ARRIVÉE' }}</span><span>{{ $fcfa($remaining) }} FCFA</span></div>
            @endif

            {{-- Included perks snapshotted on the file (Configuration → Avantages inclus, 2026-10-25). --}}
            @if ($reservation->perkLabels() !== [])
                <hr class="sep">
                <div class="strong">AVANTAGES INCLUS</div>
                @foreach ($reservation->perkLabels() as $perk)
                    <div class="perk">• {{ $perk }}</div>
                @endforeach
            @endif

            <hr class="sep">
            @if ($receipt['footer'])
                <div class="center strong">{{ $receipt['footer'] }}</div>
            @endif
            {{-- Fixed notice on every ticket (client request, 2026-10-19), not a setting. --}}
            <div class="center strong no-refund">Le ticket vendu ne sera ni échangé ni remboursé</div>
            <div class="center muted" style="margin-top: 1.5mm;">Imprimé le {{ now()->format('d/m/Y à H\hi') }}</div>
        </div>
    </div>
</body>
</html>
