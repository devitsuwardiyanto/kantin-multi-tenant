<?php

namespace App\Modules\Payments\Data;

/**
 * Permintaan pembuatan tagihan (charge) ke gateway — bebas provider. reference = kunci
 * idempoten milik platform; amount = integer Rupiah; gateway tidak menentukan harga.
 * splits = rincian per tenant (UC-07 langkah 1); jumlah gross seluruh split = amount.
 *
 * @phpstan-type Split array{tenant_id: int, gross: int, commission: int, net: int}
 */
final readonly class PaymentChargeRequest
{
    /**
     * @param  list<Split>  $splits
     */
    public function __construct(
        public string $reference,
        public string $orderNumber,
        public int $amount,
        public int $expiresInSeconds = 900,
        public array $splits = [],
    ) {}
}
