<?php

namespace App\Modules\Ordering\Services;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\TenantOrder;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Ordering\Data\CartLine;
use App\Modules\Ordering\Data\CheckoutResult;
use App\Modules\Ordering\Exceptions\CheckoutException;
use App\Support\Tokens\OpaqueToken;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mengubah keranjang (Redis) menjadi order terpersist secara ATOMIK. Prinsip:
 *
 *  - Revalidasi ulang keranjang dari basis data (harga/stok/ketersediaan); baris bermasalah
 *    membatalkan seluruh checkout — tak ada order parsial.
 *  - Satu checkout dapat lintas tenant → dipecah menjadi beberapa tenant_orders; setiap tenant
 *    mendapat SNAPSHOT komisi efektif (rate + commission_id) yang historis/tak berubah.
 *  - Harga/prep/nama dibekukan sebagai snapshot di order_items (bukan referensi ke katalog yang
 *    bisa berubah). Uang = integer Rupiah.
 *  - Idempoten: checkout_key UNIQUE. Pengiriman ganda mengembalikan order yang sama (tanpa
 *    dobel potong stok / dobel order).
 *  - Stok dipotong ATOMIK berpenjaga (WHERE stock_qty >= qty); kalah balapan → rollback.
 *  - TenantContext TIDAK aktif pada checkout anonim; tenant_id di-set eksplisit per baris.
 */
final class CheckoutService
{
    public function __construct(
        private CartService $cart,
        private AuditLogger $audit,
    ) {}

    /**
     * @throws CheckoutException
     */
    public function checkout(CustomerSession $session, string $idempotencyKey, ?CarbonInterface $scheduledAt = null): CheckoutResult
    {
        $existing = Order::query()->where('checkout_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return new CheckoutResult($existing, null, isReplay: true);
        }

        $view = $this->cart->view($session);
        if ($view->isEmpty()) {
            throw CheckoutException::emptyCart();
        }
        if ($view->hasBlockingIssues) {
            throw CheckoutException::cartNotOrderable();
        }

        /** @var Canteen $canteen */
        $canteen = Canteen::query()->findOrFail($session->canteen_id);
        $taxRate = (float) $canteen->tax_rate;
        $feeRate = (float) $canteen->service_fee_rate;

        // Kelompokkan baris per tenant (checkout bisa lintas tenant).
        /** @var array<int, list<CartLine>> $groups */
        $groups = [];
        foreach ($view->lines as $line) {
            $groups[$line->tenantId][] = $line;
        }

        $tracking = OpaqueToken::issue(32);

        try {
            $order = DB::transaction(function () use ($session, $canteen, $groups, $idempotencyKey, $tracking, $taxRate, $feeRate, $scheduledAt): Order {
                $order = new Order;
                $order->forceFill([
                    'public_id' => (string) Str::uuid(),
                    'order_number' => $this->orderNumber(),
                    'canteen_id' => $canteen->id,
                    'customer_session_id' => $session->id,
                    'checkout_key' => $idempotencyKey,
                    'tracking_token_hash' => $tracking['hash'],
                    'status' => 'awaiting_payment',
                    'subtotal_amount' => 0,
                    'tax_amount' => 0,
                    'service_fee_amount' => 0,
                    'grand_total_amount' => 0,
                    'customer_snapshot' => ['session_id' => $session->id],
                    'table_snapshot' => $this->tableSnapshot($session),
                    'placed_at' => now(),
                ])->save();

                $orderSubtotal = 0;
                $orderTax = 0;
                $orderFee = 0;

                foreach ($groups as $tenantId => $lines) {
                    $commission = $this->activeCommission($tenantId);
                    $rate = (float) $commission->commission_rate;

                    $tenantSubtotal = array_sum(array_map(static fn (CartLine $l): int => $l->lineTotal, $lines));
                    $tenantTax = (int) round($tenantSubtotal * $taxRate);
                    $tenantFee = (int) round($tenantSubtotal * $feeRate);
                    $commissionAmount = (int) round($tenantSubtotal * $rate);
                    $netAmount = $tenantSubtotal - $commissionAmount;

                    $tenantOrder = new TenantOrder;
                    $tenantOrder->forceFill([
                        'order_id' => $order->id,
                        'tenant_id' => $tenantId,
                        'commission_id' => $commission->id,
                        'status' => 'pending',
                        'scheduled_at' => $scheduledAt,
                        'commission_rate_snapshot' => $commission->commission_rate,
                        'subtotal_amount' => $tenantSubtotal,
                        'tax_amount' => $tenantTax,
                        'service_fee_amount' => $tenantFee,
                        'commission_amount' => $commissionAmount,
                        'net_amount' => $netAmount,
                    ])->save();

                    foreach ($lines as $line) {
                        $this->reserveStock($tenantId, $line, $idempotencyKey);
                        $this->persistItem($tenantId, $tenantOrder->id, $line);
                    }

                    $this->audit->record('tenant_order', $tenantOrder->id, 'placed', null, [
                        'order_id' => $order->id,
                        'subtotal' => $tenantSubtotal,
                        'commission' => $commissionAmount,
                        'net' => $netAmount,
                    ], $tenantId, $canteen->id);

                    $orderSubtotal += $tenantSubtotal;
                    $orderTax += $tenantTax;
                    $orderFee += $tenantFee;
                }

                $order->forceFill([
                    'subtotal_amount' => $orderSubtotal,
                    'tax_amount' => $orderTax,
                    'service_fee_amount' => $orderFee,
                    'grand_total_amount' => $orderSubtotal + $orderTax + $orderFee,
                ])->save();

                return $order;
            });
        } catch (UniqueConstraintViolationException) {
            // Balapan pada checkout_key: kembalikan order yang sudah ada (idempoten).
            $order = Order::query()->where('checkout_key', $idempotencyKey)->firstOrFail();

            return new CheckoutResult($order, null, isReplay: true);
        }

        $this->cart->clear($session);

        return new CheckoutResult($order, $tracking['plain']);
    }

    /**
     * Potong stok menu secara atomik + catat movement idempoten. Kalah balapan (stok kurang saat
     * commit) → CheckoutException → rollback seluruh transaksi.
     */
    private function reserveStock(int $tenantId, CartLine $line, string $checkoutKey): void
    {
        $affected = Menu::query()
            ->withoutGlobalScope('tenant')
            ->where('id', $line->menuId)
            ->where('tenant_id', $tenantId)
            ->where('stock_qty', '>=', $line->quantity)
            ->decrement('stock_qty', $line->quantity);

        if ($affected === 0) {
            throw CheckoutException::insufficientStock();
        }

        DB::table('menu_stock_movements')->insert([
            'tenant_id' => $tenantId,
            'menu_id' => $line->menuId,
            'idempotency_key' => sha1($checkoutKey.':'.$line->lineKey),
            'type' => 'sale',
            'quantity_delta' => -$line->quantity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function persistItem(int $tenantId, int $tenantOrderId, CartLine $line): void
    {
        $item = new OrderItem;
        $item->forceFill([
            'tenant_id' => $tenantId,
            'tenant_order_id' => $tenantOrderId,
            'menu_id' => $line->menuId,
            'name_snapshot' => $line->name,
            'unit_price_snapshot' => $line->unitPrice,
            'prep_minutes_snapshot' => $line->prepMinutes,
            'quantity' => $line->quantity,
            'modifier_total' => $line->modifierTotal,
            'line_total' => $line->lineTotal,
        ])->save();

        foreach ($line->modifiers as $modifier) {
            $snapshot = new OrderItemModifier;
            $snapshot->forceFill([
                'tenant_id' => $tenantId,
                'order_item_id' => $item->id,
                'modifier_group_id' => $modifier['group_id'],
                'modifier_option_id' => $modifier['id'],
                'group_name_snapshot' => $modifier['group_name'],
                'option_name_snapshot' => $modifier['name'],
                'price_delta_snapshot' => $modifier['price_delta'],
            ])->save();
        }
    }

    /**
     * Skema komisi efektif untuk tenant saat ini (effective-dated). Tidak ada skema aktif →
     * checkout dibatalkan (integritas komisi wajib).
     */
    private function activeCommission(int $tenantId): CommissionScheme
    {
        $scheme = CommissionScheme::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('valid_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>', now());
            })
            ->orderByDesc('valid_from')
            ->first();

        if ($scheme === null) {
            throw CheckoutException::commissionNotConfigured();
        }

        return $scheme;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tableSnapshot(CustomerSession $session): ?array
    {
        $table = $session->diningTable;
        if ($table === null) {
            return null;
        }

        return ['code' => $table->code, 'label' => $table->label, 'zone' => $table->zone];
    }

    private function orderNumber(): string
    {
        return 'ORD-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
    }
}
