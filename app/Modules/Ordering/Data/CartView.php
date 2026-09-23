<?php

namespace App\Modules\Ordering\Data;

/**
 * Tampilan keranjang terrevalidasi untuk satu sesi pelanggan. subtotal hanya menjumlah
 * baris yang masih dapat dipesan (available), sehingga tak pernah menagih item bermasalah.
 */
final readonly class CartView
{
    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public array $lines,
        public int $subtotal,
        public int $totalQuantity,
        public bool $hasBlockingIssues,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * Keranjang siap checkout: ada isi dan tak ada baris bermasalah.
     */
    public function isOrderable(): bool
    {
        return ! $this->isEmpty() && ! $this->hasBlockingIssues;
    }
}
