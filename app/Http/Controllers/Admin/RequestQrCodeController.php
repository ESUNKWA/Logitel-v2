<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\QrCode;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The QR code guests scan in the lobby to open the room request page (/demande), as a printable
 * poster and as an SVG file (for a print shop). The address is the one staff are using right
 * now (the VPS address or domain), so it follows the deployment without configuration.
 */
class RequestQrCodeController extends Controller
{
    public function show(): View
    {
        $url = route('stay-request.index');

        return view('admin.qr-code.show', [
            'url' => $url,
            'svg' => QrCode::svg($url),
            'localOnly' => (bool) preg_match('#^https?://(localhost|127\.|0\.0\.0\.0|\[::1\])#i', $url),
        ]);
    }

    public function download(): Response
    {
        return response(QrCode::svg(route('stay-request.index'), 1200), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-code-demande-chambre.svg"',
        ]);
    }
}
