<?php

namespace App\Modules\Ordering\Data;

use App\Models\Order;

/**
 * Hasil checkout. trackingToken (mentah) hanya terisi pada pembuatan order BARU — diberikan
 * sekali ke pelanggan untuk melacak pesanan tanpa autentikasi; pada replay idempoten bernilai null.
 */
final readonly class CheckoutResult
{
    public function __construct(
        public Order $order,
        public ?string $trackingToken,
        public bool $isReplay = false,
    ) {}
}
