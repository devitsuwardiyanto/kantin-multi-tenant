<?php

namespace App\Events;

use App\Models\TenantOrder;
use App\Support\Realtime\TenantChannels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disiarkan saat pesanan tenant BARU masuk antrean dapur (setelah pembayaran lunas & settlement).
 * ShouldDispatchAfterCommit: hanya disiarkan bila transaksi settlement benar-benar commit.
 */
final class NewTenantOrderReceived implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'NewTenantOrderReceived';
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
