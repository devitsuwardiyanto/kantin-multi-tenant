<?php

namespace App\Modules\Ordering\Services;

use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TenantOrder;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Catalog\Events\CatalogChanged;
use App\Modules\Ordering\Exceptions\CheckoutException;
use Illuminate\Support\Facades\DB;

/**
 * Pembatalan pesanan yang BELUM dibayar (tombol "Batalkan pesanan" pada layar QRIS UC-07).
 * Order + tenant_orders → cancelled dan stok yang dipotong saat checkout dikembalikan dengan
 * movement idempoten, dalam satu transaksi. Pesanan yang sudah dibayar tidak dapat dibatalkan.
 */
final class CancelUnpaidOrder
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @throws CheckoutException
     */
    public function cancel(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'awaiting_payment') {
                throw CheckoutException::notCancellable();
            }

            $locked->forceFill(['status' => 'cancelled'])->save();

            $tenantOrderIds = TenantOrder::query()->withoutGlobalScope('tenant')->where('order_id', $locked->id)->pluck('id');
            TenantOrder::query()->withoutGlobalScope('tenant')->whereIn('id', $tenantOrderIds)->update(['status' => 'cancelled']);

            $items = OrderItem::query()->withoutGlobalScope('tenant')->whereIn('tenant_order_id', $tenantOrderIds)->get();
            foreach ($items as $item) {
                Menu::query()->withoutGlobalScope('tenant')->whereKey($item->menu_id)->where('tenant_id', $item->tenant_id)
                    ->increment('stock_qty', $item->quantity);

                // Stok kembali dari nol → menu dapat dipesan lagi; katalog pelanggan diperbarui (UC-14).
                if ((int) Menu::query()->withoutGlobalScope('tenant')->whereKey($item->menu_id)->value('stock_qty') === (int) $item->quantity) {
                    event(new CatalogChanged((int) $locked->canteen_id, (int) $item->menu_id));
                }

                DB::table('menu_stock_movements')->insertOrIgnore([
                    'tenant_id' => $item->tenant_id,
                    'menu_id' => $item->menu_id,
                    'order_item_id' => $item->id,
                    'idempotency_key' => 'cancel:'.$item->id,
                    'type' => 'adjustment',
                    'quantity_delta' => $item->quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->audit->record('order', $locked->id, 'cancelled_unpaid', ['status' => 'awaiting_payment'], ['status' => 'cancelled'], null, $locked->canteen_id);
        });
    }
}
