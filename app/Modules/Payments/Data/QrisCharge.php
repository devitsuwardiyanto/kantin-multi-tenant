<?php

namespace App\Modules\Payments\Data;

use Carbon\CarbonInterface;

/**
 * Hasil charge QRIS dari gateway: referensi provider (unik), payload QRIS dinamis (string
 * EMVCo untuk dirender jadi QR), dan waktu kedaluwarsa.
 */
final readonly class QrisCharge
{
    public function __construct(
        public string $providerReference,
        public string $qrisPayload,
        public CarbonInterface $expiresAt,
    ) {}
}
