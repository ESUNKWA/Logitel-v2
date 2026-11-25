@php
    $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $paid = (float) $invoice->amountPaid();
    $remaining = (float) $invoice->remainingAmount();
    $title = $booking->isCancelled() ? 'RÉSERVATION ANNULÉE' : ($booking->isConfirmed() ? 'CONFIRMATION DE RÉSERVATION' : 'DEVIS');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} {{ $invoice->number }} — {{ $hotelInfo->name() }}</title>
    <style>
        @page { size: A4; margin: 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9edf3; color: #111; font-family: 'Helvetica Neue', Arial, 'Liberation Sans', sans-serif; font-size: 13px; line-height: 1.45; }
        .toolbar { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #15264a; }
        .toolbar button { font: inherit; font-weight: 600; border: 0; border-radius: 8px; padding: 8px 16px; cursor: pointer; }
        .toolbar .print { background: #c9a24b; color: #15264a; }
        .toolbar .close { background: rgba(255, 255, 255, .15); color: #fff; }
        .page { width: 210mm; min-height: 297mm; margin: 18px auto; padding: 16mm; background: #fff; box-shadow: 0 4px 18px rgba(15, 27, 45, .18); }
        .head { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #15264a; padding-bottom: 10px; margin-bottom: 18px; }
        .hotel { font-size: 20px; font-weight: 800; text-transform: uppercase; }
        .muted { color: #555; }
        h1 { font-size: 20px; letter-spacing: 1px; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { padding: 7px 8px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f2f4f8; }
        .r { text-align: right; white-space: nowrap; }
        .total td { font-weight: 800; font-size: 15px; border-top: 2px solid #111; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; }
        .footer { margin-top: 28px; font-size: 12px; }
        @media print { body { background: #fff; } .toolbar { display: none; } .page { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="print" onclick="window.print()">🖨 Imprimer</button>
        <button type="button" class="close" onclick="if (history.length > 1) { history.back(); } else { window.close(); }">Fermer</button>
    </div>
    <div class="page">
        <div class="head">
            <div>
                <div class="hotel">{{ $hotelInfo->name() }}</div>
                @if ($hotelInfo->fullAddress())<div class="muted">{{ $hotelInfo->fullAddress() }}</div>@endif
                @if ($hotelInfo->phones())<div class="muted">Tél. {{ implode(' / ', array_map([\App\Support\PhoneFormat::class, 'display'], $hotelInfo->phones())) }}</div>@endif
                @if ($hotelInfo->legalLine())<div class="muted">{{ $hotelInfo->legalLine() }}</div>@endif
            </div>
            <div style="text-align: right;">
                <h1>{{ $title }}</h1>
                <div>N° {{ $invoice->number }}</div>
                <div class="muted">Émis le {{ now()->format('d/m/Y') }}</div>
            </div>
        </div>

        <div class="grid">
            <div><strong>Client :</strong> {{ $booking->client_name }}</div>
            <div><strong>Téléphone :</strong> {{ \App\Support\PhoneFormat::display($booking->client_phone) }}</div>
            @if ($booking->organization)<div><strong>Organisation :</strong> {{ $booking->organization }}</div>@endif
            @if ($booking->title)<div><strong>Objet :</strong> {{ $booking->title }}</div>@endif
            <div><strong>Salle :</strong> {{ $booking->venue?->name }}</div>
            <div><strong>Date :</strong> {{ $booking->start_at->format('d/m/Y') }}, {{ $booking->start_at->format('H\hi') }} – {{ $booking->end_at->format('H\hi') }}</div>
            <div><strong>Formule :</strong> {{ $booking->formulaLabel() }}</div>
            <div><strong>Participants :</strong> {{ $booking->participants }}</div>
        </div>

        <table>
            <thead><tr><th>Désignation</th><th class="r">Prix unitaire</th><th class="r">Qté</th><th class="r">Montant</th></tr></thead>
            <tbody>
                @foreach ($sale->lines as $line)
                    <tr><td>{{ $line->label }}</td><td class="r">{{ $fcfa($line->unit_price) }}</td><td class="r">{{ $line->quantity }}</td><td class="r">{{ $fcfa($line->line_total) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot>
                @if ((float) $sale->discount_amount > 0)
                    <tr><td colspan="3">Remise{{ $sale->discount_reason ? ' — '.$sale->discount_reason : '' }}</td><td class="r">− {{ $fcfa($sale->discount_amount) }}</td></tr>
                @endif
                <tr class="total"><td colspan="3">TOTAL (FCFA)</td><td class="r">{{ $fcfa($sale->total_amount) }}</td></tr>
                @if ($paid > 0)
                    <tr><td colspan="3">Déjà versé</td><td class="r">{{ $fcfa($paid) }}</td></tr>
                    <tr><td colspan="3"><strong>Reste à payer</strong></td><td class="r"><strong>{{ $fcfa($remaining) }}</strong></td></tr>
                @endif
            </tfoot>
        </table>

        <div class="footer muted">
            @if (! $booking->isConfirmed() && ! $booking->isCancelled())
                Devis valable sous réserve de disponibilité de la salle jusqu'à confirmation de la réservation.
            @endif
            @if (config('hotel.receipt.footer'))
                <div style="margin-top: 6px;">{{ config('hotel.receipt.footer') }}</div>
            @endif
        </div>
    </div>
</body>
</html>
