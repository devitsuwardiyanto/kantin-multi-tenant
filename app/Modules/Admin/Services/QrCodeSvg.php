<?php

namespace App\Modules\Admin\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * UC-22 langkah 4: gambar QR Code siap cetak. SVG adalah grafik vektor sehingga tetap tajam
 * pada resolusi cetak berapa pun (memenuhi syarat minimal 300 dpi) tanpa ekstensi Imagick.
 */
final class QrCodeSvg
{
    public function render(string $url, int $size = 480): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($url);
    }
}
