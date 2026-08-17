<?php

namespace App\Events;

use App\Models\TenantOrder;
use App\Support\Realtime\TenantChannels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disiarkan saat status sebuah tenant_order berubah (transisi dapur). Channel privat per tenant
 * agar hanya operator tenant tersebut menerima pembaruan.
 */
final class TenantOrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TenantOrder $tenantOrder) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(TenantChannels::orders((int) $this->tenantOrder->tenant_id))];
    }

    public function broadcastAs(): string
    {
        return 'TenantOrderStatusChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'tenant_order_id' => $this->tenantOrder->id,
            'status' => $this->tenantOrder->status,
        ];
    }
}
