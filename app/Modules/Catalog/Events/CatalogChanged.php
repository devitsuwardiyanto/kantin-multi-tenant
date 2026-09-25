<?php

namespace App\Modules\Catalog\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * UC-01 mutu / UC-13 langkah 6 / UC-14 langkah 3: perubahan katalog (ketersediaan, stok habis,
 * atau data menu) disiarkan ke seluruh sesi pelanggan di kantin terkait lewat WebSocket.
 * Channel publik karena katalog memang informasi publik; payload hanya berisi ID menu.
 * ShouldDispatchAfterCommit: tidak ada siaran untuk perubahan yang akhirnya di-rollback.
 */
final class CatalogChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $canteenId, public int $menuId) {}

    public static function channelName(int $canteenId): string
    {
        return 'canteen.'.$canteenId.'.catalog';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel(self::channelName($this->canteenId))];
    }

    public function broadcastAs(): string
    {
        return 'CatalogChanged';
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return ['menu_id' => $this->menuId];
    }
}
