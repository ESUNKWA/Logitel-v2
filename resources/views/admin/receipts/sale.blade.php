@php
    $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $paid = (float) $invoice->amountPaid();
    $received = (float) $invoice->payments->sum(fn ($p) => $p->amount_received ?? $p->amount);
    $change = (float) $invoice->payments->sum(fn ($p) => $p->change_given ?? 0);
    $remaining = (float) $invoice->remainingAmount();
    $receipt = config('hotel.receipt');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Reçu {{ $invoice->number }} — {{ $hotelInfo->name() }}</title>
    @include('admin.receipts._style')
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
            @include('admin.receipts._header')

            <hr class="sep">
            <div class="doc-title">{{ mb_strtoupper($sale->department->getLabel()) }}</div>
            <div class="row"><span>Reçu N°</span><span class="strong">{{ $invoice->number }}</span></div>
            <div class="row"><span>Date</span><span>{{ $sale->sold_at->format('d/m/Y H\hi') }}</span></div>
            @if ($sale->label)
                <div class="row"><span>Réf.</span><span>{{ $sale->label }}</span></div>
            @endif
            @if ($sale->reservation)
                <div class="row"><span>Chambre</span><span class="strong">N°{{ $sale->reservation->room?->number }}</span></div>
            @endif

            <hr class="sep">
            @foreach ($sale->lines as $line)
                <div class="row"><span>{{ $line->quantity }} × {{ $line->label }}</span><span>{{ $fcfa($line->line_total) }}</span></div>
            @endforeach
            @if ((float) $sale->discount_amount > 0)
                <div class="row"><span>Sous-total</span><span>{{ $fcfa($sale->subtotal) }}</span></div>
                <div class="row"><span>Remise</span><span>− {{ $fcfa($sale->discount_amount) }}</span></div>
            @endif
            <div class="row total"><span>TOTAL</span><span>{{ $fcfa($sale->total_amount) }} FCFA</span></div>

            @if ($invoice->payments->isNotEmpty())
                <hr class="sep">
                <div class="row"><span>Montant reçu</span><span>{{ $fcfa($received) }} FCFA</span></div>
                <div class="row"><span>Monnaie rendue</span><span>{{ $fcfa($change) }} FCFA</span></div>
                <div class="row strong"><span>Montant payé</span><span>{{ $fcfa($paid) }} FCFA</span></div>
            @endif

            @if ($sale->isCancelled())
                <div class="stamp">ANNULÉ</div>
            @elseif ($remaining <= 0)
                <div class="stamp">SOLDÉ</div>
            @elseif ($sale->reservation)
                <div class="row total"><span>REPORTÉ CHAMBRE</span><span>{{ $fcfa($remaining) }} FCFA</span></div>
            @else
                <div class="row total"><span>RESTE DÛ</span><span>{{ $fcfa($remaining) }} FCFA</span></div>
            @endif

            <hr class="sep">
            @if ($receipt['footer'])
                <div class="center strong">{{ $receipt['footer'] }}</div>
            @endif
            <div class="center strong no-refund">Le ticket vendu ne sera ni échangé ni remboursé</div>
            <div class="center muted" style="margin-top: 1.5mm;">Imprimé le {{ now()->format('d/m/Y à H\hi') }}</div>
        </div>
    </div>
</body>
</html>
