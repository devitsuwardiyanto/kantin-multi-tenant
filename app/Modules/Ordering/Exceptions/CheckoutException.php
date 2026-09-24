<?php

namespace App\Modules\Ordering\Exceptions;

use RuntimeException;

/**
 * Kegagalan checkout yang membatalkan seluruh transaksi (atomik: tak ada order parsial).
 * Pesan aman untuk pelanggan; detail teknis tidak dibocorkan.
 */
final class CheckoutException extends RuntimeException
{
    public static function emptyCart(): self
    {
        return new self('Keranjang kosong.');
    }

    public static function cartNotOrderable(): self
    {
        return new self('Ada item yang tidak dapat dipesan. Perbarui keranjang lalu coba lagi.');
    }

    public static function insufficientStock(): self
    {
        return new self('Stok berubah saat checkout. Perbarui keranjang lalu coba lagi.');
    }

    public static function tenantClosed(string $tenantName): self
    {
        return new self("{$tenantName} sedang tutup. Hapus itemnya atau pilih Pesan dulu / Pick-up.");
    }

    public static function pickupNeedsSchedule(): self
    {
        return new self('Pilih waktu pengambilan untuk Pesan dulu / Pick-up.');
    }

    public static function notCancellable(): self
    {
        return new self('Pesanan sudah dibayar atau dibatalkan sehingga tidak dapat dibatalkan.');
    }

    public static function commissionNotConfigured(): self
    {
        return new self('Konfigurasi komisi tenant belum tersedia.');
    }
}
