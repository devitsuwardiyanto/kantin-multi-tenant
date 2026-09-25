<?php

namespace App\Modules\Payments\Exceptions;

use RuntimeException;

/**
 * Kegagalan domain tindak lanjut pengelola (peninjauan pembayaran, pengembalian dana).
 * Pesan aman ditampilkan kepada pengelola kantin.
 */
final class FollowUpException extends RuntimeException
{
    public static function notFound(): self
    {
        return new self('Data tidak ditemukan di kantin Anda.');
    }

    public static function notReviewable(): self
    {
        return new self('Pembayaran ini tidak sedang menunggu peninjauan.');
    }

    public static function cannotAccept(): self
    {
        return new self('Pesanan sudah tidak menunggu pembayaran; pembayaran hanya dapat dikembalikan.');
    }

    public static function noteRequired(): self
    {
        return new self('Catatan tindak lanjut wajib diisi.');
    }

    public static function notRefundable(): self
    {
        return new self('Sub-pesanan ini tidak menunggu pengembalian dana.');
    }

    public static function insufficientTenantBalance(): self
    {
        return new self('Saldo tenant tidak mencukupi untuk membalik pendapatan (dana mungkin sudah ditarik). Selesaikan dengan tenant terlebih dahulu.');
    }
}
