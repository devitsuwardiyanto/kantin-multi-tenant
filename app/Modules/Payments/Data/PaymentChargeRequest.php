<?php

namespace App\Modules\Payments\Data;

/**
 * Permintaan pembuatan tagihan (charge) ke gateway — bebas provider. reference = kunci
 * idempoten milik platform; amount = integer Rupiah; gateway tidak menentukan harga.
 */
final readonly class PaymentChargeRequest
{
    public function __construct(
        public string $reference,
        public string $orderNumber,
        public int $amount,
        public int $expiresInSeconds = 900,
    ) {}
}
