<?php

namespace App\Modules\Kitchen\Services;

use App\Events\TenantOrderStatusChanged;
use App\Models\TenantOrder;
use App\Modules\Admin\Services\AuditLogger;
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
     *
     * @var array<string, list<string>>
     */
    private const FLOW = [
        'pending' => ['accepted', 'cancelled'],
        'accepted' => ['preparing', 'cancelled'],
        'preparing' => ['ready'],
        'ready' => ['completed'],
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
    public function advance(TenantOrder $tenantOrder, string $target): TenantOrder
    {
        $from = (string) $tenantOrder->status;
        if (! in_array($target, self::nextStates($from), true)) {
            throw KitchenException::invalidTransition($from, $target);
        }

        return DB::transaction(function () use ($tenantOrder, $from, $target): TenantOrder {
            $tenantOrder->forceFill(['status' => $target])->save();

            $this->audit->record('tenant_order', $tenantOrder->id, 'kitchen_'.$target, ['status' => $from], ['status' => $target], (int) $tenantOrder->tenant_id);

            event(new TenantOrderStatusChanged($tenantOrder));

            return $tenantOrder;
        });
    }
}
