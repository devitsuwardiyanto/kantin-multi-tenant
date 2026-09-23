<?php

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/**
 * Kegagalan domain penarikan dana.
 */
final class WithdrawalException extends RuntimeException
{
    public static function alreadyActive(): self
    {
        return new self('Masih ada permintaan penarikan yang berjalan. Selesaikan dahulu.');
    }

    public static function insufficientFunds(): self
    {
        return new self('Saldo tersedia tidak mencukupi.');
    }

    public static function invalidAmount(): self
    {
        return new self('Nominal penarikan tidak valid.');
    }

    public static function accountNotUsable(): self
    {
        return new self('Rekening tujuan tidak valid atau belum terverifikasi.');
    }

    public static function notReviewable(): self
    {
        return new self('Penarikan tidak dalam status yang dapat ditinjau.');
    }
}
