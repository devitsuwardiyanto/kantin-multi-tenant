<?php

namespace App\Modules\Catalog\Services;

use App\Models\Menu;
use App\Modules\Admin\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Perubahan stok menu bersifat atomik + auditable: update stock_qty, catat movement
 * idempoten (unique idempotency_key), dan tulis audit — dalam satu transaksi + row lock.
 */
final class MenuStockService
{
    public function __construct(private AuditLogger $audit) {}

    public function adjust(Menu $menu, int $quantityDelta, string $type, string $idempotencyKey, ?string $reason = null): Menu
    {
        return DB::transaction(function () use ($menu, $quantityDelta, $type, $idempotencyKey, $reason): Menu {
            $locked = Menu::query()->withoutGlobalScope('tenant')->lockForUpdate()->findOrFail($menu->id);

            DB::table('menu_stock_movements')->insert([
                'tenant_id' => $locked->tenant_id,
                'menu_id' => $locked->id,
                'idempotency_key' => $idempotencyKey,
                'type' => $type,
                'quantity_delta' => $quantityDelta,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $locked->forceFill(['stock_qty' => $locked->stock_qty + $quantityDelta])->save();

            $this->audit->record('menu', $locked->id, 'stock_'.$type,
                null, ['delta' => $quantityDelta, 'reason' => $reason, 'stock_qty' => $locked->stock_qty], $locked->tenant_id);

            return $locked;
        });
    }

    public function toggleAvailability(Menu $menu): Menu
    {
        $menu->forceFill(['is_available' => ! $menu->is_available])->save();

        $this->audit->record('menu', $menu->id, 'toggle_availability',
            null, ['is_available' => $menu->is_available], $menu->tenant_id);

        return $menu;
    }
}
