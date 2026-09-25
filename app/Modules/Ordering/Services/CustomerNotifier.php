<?php

namespace App\Modules\Ordering\Services;

use App\Models\Order;
use App\Models\TenantOrder;
use App\Modules\Kitchen\Services\KitchenService;
use App\Modules\Ordering\Contracts\NotificationGateway;
use App\Modules\Ordering\Jobs\SendCustomerNotification;
use Illuminate\Support\Facades\DB;

/**
 * UC-12 Kirim Notifikasi Status: menyusun pesan (nomor pesanan, nama tenant, status terbaru),
 * mencatatnya di notification_deliveries (unik per event + saluran → tidak dobel), lalu
 * mengantrikan pengiriman. Tanpa nomor WhatsApp → tidak dikirim (prasyarat 2); status tetap
 * dapat dilihat di halaman pelacakan (4b).
 */
final class CustomerNotifier
{
    /** Status sub-pesanan yang signifikan untuk dinotifikasi. */
    private const STATUS_TEXT = [
        'accepted' => 'sudah DITERIMA dapur.',
        'preparing' => 'sedang DIMASAK.',
        'ready' => 'sudah SIAP DIAMBIL di konter. Tunjukkan nomor pesanan Anda, ya. ✅',
    ];

    public function paymentVerified(Order $order): ?int
    {
        $url = route('customer.order.show', ['canteen' => $order->canteen->slug]);
        $amount = number_format((int) $order->grand_total_amount, 0, ',', '.');

        return $this->queue($order, null, 'order:'.$order->id.':paid',
            "Pembayaran Rp{$amount} untuk pesanan #{$order->order_number} diterima. Pesanan diteruskan ke dapur. Lacak: {$url}");
    }

    public function statusChanged(TenantOrder $tenantOrder): ?int
    {
        $order = $tenantOrder->order;
        $tenant = (string) $tenantOrder->tenant?->display_name;

        $message = match (true) {
            isset(self::STATUS_TEXT[$tenantOrder->status]) => "Halo {$this->name($order)}! Pesanan #{$order->order_number} dari {$tenant} ".self::STATUS_TEXT[$tenantOrder->status],
            $tenantOrder->status === 'cancelled' => "Maaf, pesanan #{$order->order_number} dari {$tenant} dibatalkan (".(KitchenService::CANCEL_REASONS[$tenantOrder->cancel_reason ?? ''] ?? 'dibatalkan tenant').'). Dana akan dikembalikan oleh pengelola kantin.',
            default => null,
        };

        return $message === null ? null : $this->queue($order, $tenantOrder, 'tenant_order:'.$tenantOrder->id.':'.$tenantOrder->status, $message);
    }

    /** UC-06 langkah 6: pengingat saat pesanan pre-order mulai dimasak. */
    public function preOrderReminder(TenantOrder $tenantOrder): ?int
    {
        $order = $tenantOrder->order;
        $time = $tenantOrder->scheduled_at?->setTimezone((string) config('app.display_timezone'))->format('H.i');

        return $this->queue($order, $tenantOrder, 'tenant_order:'.$tenantOrder->id.':reminder',
            "Pengingat: pesanan pre-order #{$order->order_number} dari {$tenantOrder->tenant?->display_name} mulai dimasak dan siap sekitar pukul {$time}.");
    }

    private function queue(Order $order, ?TenantOrder $tenantOrder, string $eventKey, string $message): ?int
    {
        $to = $order->customer_snapshot['whatsapp'] ?? null;
        if (! is_string($to) || $to === '') {
            return null;
        }

        $channel = app(NotificationGateway::class)->channel();
        $inserted = DB::table('notification_deliveries')->insertOrIgnore([
            'order_id' => $order->id,
            'customer_session_id' => $order->customer_session_id,
            'tenant_order_id' => $tenantOrder?->id,
            'event_key' => $eventKey,
            'channel' => $channel,
            'status' => 'pending',
            'attempts' => 0,
            'payload' => json_encode(['to' => $to, 'message' => $message], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted === 0) {
            return null; // sudah pernah diantrikan (idempoten)
        }

        $id = (int) DB::table('notification_deliveries')->where('event_key', $eventKey)->where('channel', $channel)->value('id');
        SendCustomerNotification::dispatch($id);

        return $id;
    }

    private function name(Order $order): string
    {
        return (string) ($order->customer_snapshot['name'] ?? 'Pelanggan');
    }
}
