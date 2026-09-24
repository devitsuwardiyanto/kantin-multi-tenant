<?php

namespace App\Modules\Payments\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * UC-07 langkah 4: payload EMVCo QRIS dirender menjadi gambar SVG yang dapat dipindai aplikasi
 * pembayaran (bacon/bacon-qr-code, sama seperti QR meja UC-22). Nominal ada di dalam payload
 * sehingga tidak dapat diubah pelanggan.
 */
final class QrisImage
{
    public function svg(string $payload, int $size = 260): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($payload);
    }
}
