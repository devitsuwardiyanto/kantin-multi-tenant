<?php

namespace App\Modules\Ordering\Data;

/**
 * Baris keranjang HASIL REVALIDASI. Harga (unitPrice, modifier delta, lineTotal) selalu
 * dihitung ulang dari basis data saat menyusun tampilan — bukan dari nilai tersimpan di Redis.
 * priceAtAdd hanya informatif (deteksi "harga berubah"), tidak pernah dipakai sebagai otoritas.
 *
 * @phpstan-type ModifierShape array{id: int, name: string, price_delta: int}
 */
final readonly class CartLine
{
    /**
     * @param  list<ModifierShape>  $modifiers
     * @param  list<string>  $issues  mis. menu_unavailable|insufficient_stock|price_changed|modifier_unavailable
     */
    public function __construct(
        public string $lineKey,
        public int $menuId,
        public int $tenantId,
        public string $tenantName,
        public string $name,
        public int $quantity,
        public int $unitPrice,
        public int $priceAtAdd,
        public array $modifiers,
        public int $modifierTotal,
        public int $lineTotal,
        public bool $available,
        public array $issues,
    ) {}

    public function priceChanged(): bool
    {
        return in_array('price_changed', $this->issues, true);
    }
}
