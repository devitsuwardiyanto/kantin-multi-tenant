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
        return new self('Masih ada pengajuan penarikan yang berjalan. Tunggu hingga selesai diproses pengelola.');
    }

    public static function insufficientFunds(int $available): self
    {
        return new self('Saldo tersedia tidak mencukupi. Saldo tersedia saat ini: Rp'.number_format($available, 0, ',', '.').'.');
    }

    public static function belowMinimum(int $minimum): self
    {
        return new self('Nominal penarikan minimum Rp'.number_format($minimum, 0, ',', '.').'.');
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

    public static function proofRequired(): self
    {
        return new self('Unggah bukti transfer sebelum menyetujui pencairan.');
    }

    public static function reasonRequired(): self
    {
        return new self('Alasan penolakan wajib diisi.');
    }

    public static function ledgerMismatch(): self
    {
        return new self('Saldo tenant tidak sesuai dengan ledger. Permintaan ditandai untuk investigasi dan tidak dapat disetujui.');
    }
}
