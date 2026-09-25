<?php

namespace App\Modules\Kitchen\Services;

use App\Models\TenantOrder;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Kitchen\Events\TenantOrderStatusChanged;
use App\Modules\Kitchen\Exceptions\KitchenException;
use Illuminate\Support\Facades\DB;

/**
 * Mesin status antrean dapur untuk tenant_order. Transisi divalidasi terhadap FLOW; perubahan
 * dicatat audit dan disiarkan realtime (Reverb) ke channel privat tenant. Otorisasi keanggotaan
 * dilakukan pemanggil (komponen KDS mem-verify ulang membership + set TenantContext).
 */
final class KitchenService
{
    /**
     * Transisi yang diizinkan per status. completed & cancelled bersifat terminal.
     * UC-15: tombol "Terima" langsung memulai masak (pending → preparing); pembatalan dengan
     * alasan diizinkan sampai pesanan diserahkan (alur 4a).
     *
     * @var array<string, list<string>>
     */
    private const FLOW = [
        'pending' => ['accepted', 'preparing', 'cancelled'],
        'accepted' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['completed', 'cancelled'],
    ];

    /** Alasan pembatalan yang dapat dipilih tenant (dicatat untuk pengembalian dana). */
    public const CANCEL_REASONS = [
        'stok_habis' => 'Bahan/stok habis',
        'dapur_tutup' => 'Dapur tutup mendadak',
        'pelanggan_tidak_datang' => 'Pelanggan tidak mengambil pesanan',
        'lainnya' => 'Alasan lain',
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @return list<string>
     */
    public static function nextStates(string $status): array
    {
        return self::FLOW[$status] ?? [];
    }

    /**
     * @throws KitchenException
     */
    public function advance(TenantOrder $tenantOrder, string $target, ?string $cancelReason = null): TenantOrder
    {
        $from = (string) $tenantOrder->status;
        if (! in_array($target, self::nextStates($from), true)) {
            throw KitchenException::invalidTransition($from, $target);
        }
        if ($target === 'cancelled' && ! array_key_exists((string) $cancelReason, self::CANCEL_REASONS)) {
            throw KitchenException::cancelReasonRequired();
        }

        return DB::transaction(function () use ($tenantOrder, $from, $target, $cancelReason): TenantOrder {
            $tenantOrder->forceFill(['status' => $target] + match ($target) {
                'accepted', 'preparing' => ['accepted_at' => $tenantOrder->accepted_at ?? now()],
                'ready' => ['ready_at' => now()],
                'completed' => ['completed_at' => now()],
                'cancelled' => ['cancelled_at' => now(), 'cancel_reason' => $cancelReason, 'refund_status' => 'pending'],
                default => [],
            })->save();

            $this->audit->record('tenant_order', $tenantOrder->id, 'kitchen_'.$target, ['status' => $from], array_filter([
                'status' => $target,
                'cancel_reason' => $cancelReason,
            ]), (int) $tenantOrder->tenant_id);

            event(new TenantOrderStatusChanged($tenantOrder));

            return $tenantOrder;
        });
    }
}
