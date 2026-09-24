<?php

namespace App\Modules\Catalog\Services;

use App\Models\Canteen;
use App\Models\Tenant;
use App\Models\TenantOperatingHour;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Status buka/tutup tenant dan kantin berdasarkan `tenant_operating_hours` pada waktu lokal
 * (config app.display_timezone, default Asia/Jakarta). Dipakai UC-02 (alur 3b, kantin tutup)
 * dan halaman sambutan sesudah pindai QR. Tenant tanpa jam terkonfigurasi dianggap buka.
 */
final class TenantOpeningHours
{
    public function isOpen(Tenant $tenant, ?CarbonInterface $at = null): bool
    {
        $hours = $this->hoursFor($tenant);
        if ($hours->isEmpty()) {
            return true;
        }

        $local = $this->local($at);
        $today = $hours->firstWhere('day_of_week', $local->dayOfWeek);
        if ($today === null) {
            return false;
        }

        $now = $local->format('H:i:s');
        $opens = (string) $today->opens_at;
        $closes = (string) $today->closes_at;

        // Jam melewati tengah malam (mis. 18:00–02:00).
        return $opens <= $closes ? ($now >= $opens && $now < $closes) : ($now >= $opens || $now < $closes);
    }

    /** Label jam hari ini, mis. "10.00–21.00", atau null bila tidak dikonfigurasi/libur. */
    public function todayLabel(Tenant $tenant, ?CarbonInterface $at = null): ?string
    {
        $today = $this->hoursFor($tenant)->firstWhere('day_of_week', $this->local($at)->dayOfWeek);

        return $today === null ? null : $this->fmt((string) $today->opens_at).'–'.$this->fmt((string) $today->closes_at);
    }

    /** Jam buka hari ini bila tenant belum buka, mis. "10.00". */
    public function opensAtToday(Tenant $tenant, ?CarbonInterface $at = null): ?string
    {
        $today = $this->hoursFor($tenant)->firstWhere('day_of_week', $this->local($at)->dayOfWeek);

        return $today === null ? null : $this->fmt((string) $today->opens_at);
    }

    /**
     * Kantin buka bila sedikitnya satu tenant aktif sedang buka. Bila belum ada tenant aktif yang
     * mengatur jam operasional, kantin dianggap buka (jam belum dikonfigurasi, bukan tutup).
     */
    public function canteenIsOpen(Canteen $canteen, ?CarbonInterface $at = null): bool
    {
        $tenants = $this->activeTenants($canteen);
        if ($tenants->every(fn (Tenant $tenant): bool => $tenant->operatingHours->isEmpty())) {
            return true;
        }

        return $tenants->contains(fn (Tenant $tenant): bool => $this->isOpen($tenant, $at));
    }

    /** @return Collection<int, Tenant> */
    public function activeTenants(Canteen $canteen): Collection
    {
        return Tenant::query()
            ->where('canteen_id', $canteen->id)
            ->where('status', 'active')
            ->with(['operatingHours' => fn ($query) => $query->withoutGlobalScope('tenant')])
            ->orderBy('display_name')
            ->get();
    }

    /** @return Collection<int, TenantOperatingHour> */
    private function hoursFor(Tenant $tenant): Collection
    {
        return $tenant->relationLoaded('operatingHours')
            ? $tenant->operatingHours
            : $tenant->operatingHours()->withoutGlobalScope('tenant')->get();
    }

    private function local(?CarbonInterface $at): CarbonInterface
    {
        return ($at ?? now())->copy()->setTimezone((string) config('app.display_timezone', 'Asia/Jakarta'));
    }

    private function fmt(string $time): string
    {
        return str_replace(':', '.', substr($time, 0, 5));
    }
}
