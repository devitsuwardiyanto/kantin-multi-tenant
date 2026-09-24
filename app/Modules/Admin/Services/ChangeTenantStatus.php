<?php

namespace App\Modules\Admin\Services;

use App\Models\Tenant;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * UC-21 alur 3a: menonaktifkan/mengaktifkan tenant. Tenant nonaktif otomatis hilang dari katalog
 * publik (PublicCatalogQuery hanya membaca tenant `active`) dan operatornya tidak dapat membuka
 * portal tenant, tetapi seluruh data historis (menu, order, ledger) tetap utuh — tidak ada delete.
 */
final class ChangeTenantStatus
{
    public const ALLOWED = ['active', 'inactive'];

    public function __construct(private AuditLogger $audit) {}

    public function handle(Tenant $tenant, string $status): Tenant
    {
        if (! in_array($status, self::ALLOWED, true)) {
            throw new DomainException('Status tenant tidak dikenal.');
        }

        return DB::transaction(function () use ($tenant, $status): Tenant {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $before = $locked->status;

            if ($before === $status) {
                return $locked;
            }

            $locked->forceFill(['status' => $status])->save();

            $this->audit->record('tenant', $locked->id, $status === 'active' ? 'activated' : 'deactivated',
                ['status' => $before], ['status' => $status], $locked->id, $locked->canteen_id);

            return $locked;
        });
    }
}
