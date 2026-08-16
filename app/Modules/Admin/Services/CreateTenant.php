<?php

namespace App\Modules\Admin\Services;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\Tenant;
use App\Models\TenantBalance;
use Illuminate\Support\Facades\DB;

/**
 * Membuat tenant beserta komisi aktif awal dan balance nol secara atomik.
 */
final class CreateTenant
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Canteen $canteen, array $data): Tenant
    {
        return DB::transaction(function () use ($canteen, $data): Tenant {
            $tenant = (new Tenant)->forceFill([
                'canteen_id' => $canteen->id,
                'code' => $data['code'],
                'slug' => $data['slug'],
                'display_name' => $data['display_name'],
                'status' => 'active',
            ]);
            $tenant->save();

            (new TenantBalance)->forceFill([
                'tenant_id' => $tenant->id,
                'available_amount' => 0,
                'held_amount' => 0,
            ])->save();

            (new CommissionScheme)->forceFill([
                'tenant_id' => $tenant->id,
                'commission_rate' => $data['commission_rate'],
                'valid_from' => now(),
                'valid_to' => null,
            ])->save();

            $this->audit->record('tenant', $tenant->id, 'created',
                null, $tenant->only(['code', 'slug', 'display_name', 'status']),
                $tenant->id, $canteen->id);

            return $tenant;
        });
    }
}
