<?php

namespace App\Modules\Kitchen\Services;

use App\Models\Menu;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Models\TenantOrder;

/**
 * UC-11 Hitung Estimasi Waktu Tunggu. Estimasi = (akumulasi waktu penyiapan item pada antrean
 * aktif tenant ÷ kapasitas paralel dapur) + waktu penyiapan item yang dipesan. Tanpa antrean
 * (alur 1a) estimasi = waktu penyiapan item saja. Ditampilkan sebagai rentang menit karena
 * deviasi yang diizinkan SRS hingga 40%.
 */
final class WaitTimeEstimator
{
    /** Jumlah pesanan yang dapat dimasak bersamaan oleh satu dapur tenant. */
    public const PARALLEL_CAPACITY = 2;

    /** Status sub-pesanan yang masih menunggu atau sedang dimasak. */
    public const ACTIVE_STATUSES = ['accepted', 'preparing'];

    /** Menit antrean aktif tenant (sebelum item baru). */
    public function queueMinutes(Tenant $tenant): float
    {
        $activeOrderIds = TenantOrder::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->pluck('id');

        if ($activeOrderIds->isEmpty()) {
            return 0.0;
        }

        $totalPrep = (int) OrderItem::query()->withoutGlobalScope('tenant')
            ->whereIn('tenant_order_id', $activeOrderIds)
            ->sum('prep_minutes_snapshot');

        return $totalPrep / self::PARALLEL_CAPACITY;
    }

    /**
     * Rentang estimasi untuk tenant: antrean + rata-rata waktu siap menu yang tersedia.
     *
     * @return array{low: int, high: int}
     */
    public function forTenant(Tenant $tenant, ?float $itemPrep = null): array
    {
        $itemPrep ??= (float) (Menu::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)->where('is_available', true)->avg('prep_minutes') ?? 0);

        $estimate = $this->queueMinutes($tenant) + $itemPrep;
        $low = max(1, (int) round($estimate));

        return ['low' => $low, 'high' => max($low + 1, (int) ceil($estimate * 1.5))];
    }

    /** Label siap tampil, mis. "± 8–12 mnt". */
    public function label(Tenant $tenant, ?float $itemPrep = null): string
    {
        $range = $this->forTenant($tenant, $itemPrep);

        return "± {$range['low']}–{$range['high']} mnt";
    }
}
