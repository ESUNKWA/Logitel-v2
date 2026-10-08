<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR codes as SVG (pure PHP, no image extension needed). High error correction (H) so a
 * printed code still scans when slightly damaged or dirty.
 */
class QrCode
{
    public static function svg(string $content, int $size = 600): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 2, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0, 0, 0))),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($content, 'UTF-8', ErrorCorrectionLevel::H());
    }
}
