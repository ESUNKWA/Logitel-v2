{{-- Thermal ticket styles shared by the stay receipt and the sale receipt ($width: 58 / 80). --}}
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
