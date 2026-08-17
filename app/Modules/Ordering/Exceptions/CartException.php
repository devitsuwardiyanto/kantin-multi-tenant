<?php

namespace App\Modules\Ordering\Exceptions;

use RuntimeException;

/**
 * Kesalahan domain keranjang (mis. menu di luar kantin sesi, stok/kuantitas tak sah).
 * Pesan aman untuk ditampilkan; tidak membocorkan keberadaan resource lintas-tenant.
 */
final class CartException extends RuntimeException
{
    public static function menuNotOrderable(): self
    {
        return new self('Menu tidak tersedia untuk dipesan di kantin ini.');
    }

    public static function invalidQuantity(): self
    {
        return new self('Kuantitas tidak valid.');
    }

    public static function tooManyLines(): self
    {
        return new self('Keranjang penuh. Selesaikan pesanan ini terlebih dahulu.');
    }

    public static function modifierNotOrderable(): self
    {
        return new self('Pilihan tambahan tidak tersedia.');
    }
}
