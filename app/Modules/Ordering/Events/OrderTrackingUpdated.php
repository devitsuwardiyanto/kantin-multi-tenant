<?php

namespace App\Modules\Ordering\Events;

use App\Modules\Ordering\Realtime\OrderChannels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * UC-09 langkah 5: status sub-pesanan berubah → disiarkan ke channel privat pesanan pelanggan
 * setelah commit. Payload hanya status + nama tenant (tanpa ID internal).
 */
final class OrderTrackingUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $orderPublicId,
        public string $tenantName,
        public string $status,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(OrderChannels::order($this->orderPublicId))];
    }

    public function broadcastAs(): string
    {
        return 'OrderTrackingUpdated';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['tenant' => $this->tenantName, 'status' => $this->status];
    }
}
