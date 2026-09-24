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
        public int $taxAmount = 0,
        public int $serviceFeeAmount = 0,
    ) {}

    /** UC-03 langkah 5: total keseluruhan termasuk pajak dan biaya layanan. */
    public function grandTotal(): int
    {
        return $this->subtotal + $this->taxAmount + $this->serviceFeeAmount;
    }

    /**
     * UC-03 langkah 4: baris dikelompokkan per tenant beserta subtotal tenant tersebut.
     *
     * @return list<array{tenant_id: int, tenant_name: string, subtotal: int, lines: list<CartLine>}>
     */
    public function tenantGroups(): array
    {
        $groups = [];
        foreach ($this->lines as $line) {
            $groups[$line->tenantId] ??= ['tenant_id' => $line->tenantId, 'tenant_name' => $line->tenantName, 'subtotal' => 0, 'lines' => []];
            $groups[$line->tenantId]['lines'][] = $line;
            $groups[$line->tenantId]['subtotal'] += $line->available ? $line->lineTotal : 0;
        }

        return array_values($groups);
    }

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
