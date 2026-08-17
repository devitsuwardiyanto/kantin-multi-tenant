<?php

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/**
 * Kegagalan domain pembayaran. Pesan aman untuk pelanggan.
 */
final class PaymentException extends RuntimeException
{
    public static function orderNotPayable(): self
    {
        return new self('Pesanan tidak dalam status menunggu pembayaran.');
    }

    public static function attemptExpired(): self
    {
        return new self('Tagihan QRIS telah kedaluwarsa. Buat tagihan baru.');
    }

    public static function sandboxOnly(): self
    {
        return new self('Simulasi pembayaran hanya tersedia pada mode sandbox.');
    }
}
