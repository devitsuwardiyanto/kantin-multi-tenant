<?php

namespace App\Modules\Ordering\Listeners;

use App\Models\Order;
use App\Modules\Kitchen\Events\TenantOrderStatusChanged;
use App\Modules\Ordering\Events\OrderTrackingUpdated;
use App\Modules\Ordering\Services\CustomerNotifier;
use App\Modules\Payments\Events\PaymentVerified;

/**
 * Modul Ordering mendengarkan event modul lain (tanpa memanggil kodenya langsung):
 *  - PaymentVerified (UC-08 langkah 7) → notifikasi konfirmasi pembayaran (UC-12).
 *  - TenantOrderStatusChanged (UC-15 langkah 5) → notifikasi status + siaran ke channel
 *    pelacakan pesanan (UC-09 langkah 5).
 * Keduanya didispatch setelah commit, jadi pelanggan tak pernah melihat status yang di-rollback.
 */
final class NotifyCustomerOfOrderEvents
{
    public function __construct(private CustomerNotifier $notifier) {}

    public function onPaymentVerified(PaymentVerified $event): void
    {
        $order = Order::query()->with('canteen')->find($event->payment->order_id);
        if ($order !== null) {
            $this->notifier->paymentVerified($order);
        }
    }

    public function onTenantOrderStatusChanged(TenantOrderStatusChanged $event): void
    {
        $tenantOrder = $event->tenantOrder->loadMissing(['order', 'tenant']);

        OrderTrackingUpdated::dispatch((string) $tenantOrder->order->public_id, (string) $tenantOrder->tenant?->display_name, (string) $tenantOrder->status);
        $this->notifier->statusChanged($tenantOrder);
    }
}
