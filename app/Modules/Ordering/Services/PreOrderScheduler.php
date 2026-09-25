<?php

namespace App\Modules\Ordering\Services;

use App\Models\Tenant;
use App\Models\TenantOrder;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Catalog\Services\TenantOpeningHours;
use App\Modules\Kitchen\Events\NewTenantOrderReceived;
use App\Modules\Ordering\Events\OrderTrackingUpdated;
use App\Modules\Ordering\Exceptions\PreOrderException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * UC-06 Jadwalkan Pre-Order (extend UC-05). Waktu ambil dipilih per slot 15 menit dan wajib:
 * (1) ≥ 15 menit dari sekarang, (2) dalam jam operasional SEMUA tenant di keranjang,
 * (3) tenant mengaktifkan pre-order, (4) slot belum penuh (kapasitas per tenant).
 * Pesanan terjadwal disimpan berstatus "scheduled" dan dilepas ke antrean dapur pada
 * release_at = waktu ambil − estimasi penyiapan (perintah ordering:release-scheduled).
 */
final class PreOrderScheduler
{
    public const SLOT_MINUTES = 15;

    public const MIN_LEAD_MINUTES = 15;

    /** Hari pengambilan yang ditawarkan: hari ini + 2 hari berikutnya. */
    public const DAYS_AHEAD = 2;

    private const DAY_NAMES = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    private const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public function __construct(
        private TenantOpeningHours $hours,
        private AuditLogger $audit,
    ) {}

    /**
     * Hari yang punya sedikitnya satu slot tersedia, mis. [['date' => '2026-07-18', 'label' => 'Hari ini · Sab 18 Jul']].
     *
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array{date: string, label: string}>
     */
    public function days(Collection $tenants, ?CarbonInterface $now = null): array
    {
        $today = $this->local($now ?? now())->startOfDay();
        $days = [];

        foreach (range(0, self::DAYS_AHEAD) as $offset) {
            $day = $today->addDays($offset);
            $available = array_filter($this->slots($tenants, $day, $now), fn (array $slot): bool => $slot['state'] === 'available');
            if ($available !== []) {
                $label = self::DAY_NAMES[$day->dayOfWeek].' '.$day->day.' '.self::MONTH_NAMES[$day->month - 1];
                $days[] = ['date' => $day->toDateString(), 'label' => $offset === 0 ? 'Hari ini · '.$label : $label];
            }
        }

        return $days;
    }

    /**
     * Slot 15 menit pada satu hari (waktu lokal) selama semua tenant buka.
     *
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array{time: CarbonImmutable, label: string, state: 'available'|'past'|'full'}>
     */
    public function slots(Collection $tenants, CarbonInterface $day, ?CarbonInterface $now = null): array
    {
        $earliest = CarbonImmutable::instance($now ?? now())->addMinutes(self::MIN_LEAD_MINUTES);
        $start = $this->local($day)->startOfDay();
        $booked = $this->bookedPerSlot($tenants, $start, $start->addDay());
        $slots = [];

        for ($minute = 0; $minute < 24 * 60; $minute += self::SLOT_MINUTES) {
            $time = $start->addMinutes($minute);
            if (! $this->allOpen($tenants, $time)) {
                continue;
            }

            $state = match (true) {
                $time->lessThan($earliest) => 'past',
                $this->fullTenant($tenants, $time, $booked) !== null => 'full',
                default => 'available',
            };

            $slots[] = ['time' => $time, 'label' => $time->format('H.i'), 'state' => $state];
        }

        return $slots;
    }

    /**
     * Validasi jadwal (UC-06 alur 2 + 2a). Dipanggil lagi di dalam transaksi checkout sesudah
     * baris tenant dikunci, sehingga dua pelanggan tidak dapat mengisi slot terakhir bersamaan.
     *
     * @param  Collection<int, Tenant>  $tenants
     *
     * @throws PreOrderException
     */
    public function assertSchedulable(Collection $tenants, CarbonInterface $at, ?CarbonInterface $now = null): void
    {
        foreach ($tenants as $tenant) {
            if (! $tenant->pre_order_enabled) {
                throw PreOrderException::notEnabled($tenant->display_name);
            }
        }

        $at = $this->local($at);
        $label = $at->format('H.i');

        if ($at->second !== 0 || $at->minute % self::SLOT_MINUTES !== 0) {
            throw PreOrderException::withAlternatives("Waktu {$label} bukan slot 15 menit.", $this->nearest($tenants, $at, $now));
        }

        if ($at->lessThan(CarbonImmutable::instance($now ?? now())->addMinutes(self::MIN_LEAD_MINUTES))) {
            throw PreOrderException::withAlternatives('Waktu ambil minimal '.self::MIN_LEAD_MINUTES.' menit dari sekarang.', $this->nearest($tenants, $at, $now));
        }

        if (! $this->allOpen($tenants, $at)) {
            throw PreOrderException::withAlternatives("Waktu {$label} di luar jam operasional tenant.", $this->nearest($tenants, $at, $now));
        }

        $full = $this->fullTenant($tenants, $at, $this->bookedPerSlot($tenants, $at, $at->addMinutes(self::SLOT_MINUTES)));
        if ($full !== null) {
            throw PreOrderException::withAlternatives("Slot {$label} penuh untuk {$full->display_name}.", $this->nearest($tenants, $at, $now));
        }
    }

    /**
     * Dua slot tersedia terdekat dari waktu yang diminta (urut waktu).
     *
     * @param  Collection<int, Tenant>  $tenants
     * @return list<CarbonImmutable>
     */
    public function nearest(Collection $tenants, CarbonInterface $at, ?CarbonInterface $now = null, int $limit = 2): array
    {
        $at = CarbonImmutable::instance($at);
        $candidates = [];
        foreach ([-1, 0, 1] as $offset) {
            foreach ($this->slots($tenants, $this->local($at)->addDays($offset), $now) as $slot) {
                if ($slot['state'] === 'available' && ! $slot['time']->equalTo($at)) {
                    $candidates[] = $slot['time'];
                }
            }
        }

        usort($candidates, fn (CarbonImmutable $a, CarbonImmutable $b): int => abs($a->diffInSeconds($at)) <=> abs($b->diffInSeconds($at)));
        $picked = array_slice($candidates, 0, $limit);
        usort($picked, fn (CarbonImmutable $a, CarbonImmutable $b): int => $a <=> $b);

        return $picked;
    }

    /** Waktu mulai dimasak = waktu ambil − estimasi penyiapan (menit), dalam zona waktu aplikasi. */
    public function releaseAt(CarbonInterface $scheduledAt, int $prepMinutes): CarbonImmutable
    {
        return $this->storage($scheduledAt)->subMinutes(max(0, $prepMinutes));
    }

    /**
     * Eloquent/query builder memformat Carbon TANPA konversi zona waktu, jadi waktu lokal (WIB)
     * wajib diubah ke zona waktu aplikasi sebelum disimpan atau dibandingkan di SQL.
     */
    public function storage(CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone((string) config('app.timezone'));
    }

    /**
     * UC-06 alur 4–5: pesanan terjadwal yang SUDAH DIBAYAR dan release_at-nya tiba dilepas ke
     * antrean dapur (status "pending"). Pesanan yang belum dibayar tetap ditahan.
     */
    public function releaseDue(?CarbonInterface $now = null): int
    {
        $due = TenantOrder::query()
            ->withoutGlobalScope('tenant')
            ->where('status', 'scheduled')
            ->where('release_at', '<=', $this->storage($now ?? now()))
            ->whereHas('order', fn ($query) => $query->where('status', 'paid'))
            ->pluck('id');

        $released = 0;
        foreach ($due as $id) {
            $released += DB::transaction(function () use ($id): int {
                $tenantOrder = TenantOrder::query()->withoutGlobalScope('tenant')->lockForUpdate()->whereKey($id)->first();
                if ($tenantOrder === null || $tenantOrder->status !== 'scheduled') {
                    return 0;
                }

                $tenantOrder->forceFill(['status' => 'pending'])->save();
                $this->audit->record('tenant_order', $tenantOrder->id, 'pre_order_released', ['status' => 'scheduled'], ['status' => 'pending'], (int) $tenantOrder->tenant_id);

                // Masuk antrean dapur (UC-15) + pelacakan pelanggan (UC-09) + pengingat (UC-06 langkah 6).
                $tenantOrder->loadMissing(['order', 'tenant']);
                event(new NewTenantOrderReceived($tenantOrder));
                OrderTrackingUpdated::dispatch((string) $tenantOrder->order->public_id, (string) $tenantOrder->tenant?->display_name, 'pending');
                app(CustomerNotifier::class)->preOrderReminder($tenantOrder);

                return 1;
            });
        }

        return $released;
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     */
    private function allOpen(Collection $tenants, CarbonInterface $at): bool
    {
        return $tenants->every(fn (Tenant $tenant): bool => $this->hours->isOpen($tenant, $at));
    }

    /**
     * Tenant pertama yang slot-nya sudah penuh (pesanan terjadwal aktif ≥ kapasitas).
     *
     * @param  Collection<int, Tenant>  $tenants
     * @param  array<string, int>  $booked  "tenant_id|timestamp slot" => jumlah pesanan
     */
    private function fullTenant(Collection $tenants, CarbonInterface $slot, array $booked): ?Tenant
    {
        return $tenants->first(fn (Tenant $tenant): bool => ($booked[$tenant->id.'|'.$slot->getTimestamp()] ?? 0) >= $tenant->pre_order_slot_capacity);
    }

    /**
     * Hitung pesanan terjadwal aktif per tenant per slot dalam rentang [from, until) — satu query.
     *
     * @param  Collection<int, Tenant>  $tenants
     * @return array<string, int>
     */
    private function bookedPerSlot(Collection $tenants, CarbonInterface $from, CarbonInterface $until): array
    {
        $rows = TenantOrder::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('tenant_id', $tenants->pluck('id')->all())
            ->where('scheduled_at', '>=', $this->storage($from))
            ->where('scheduled_at', '<', $this->storage($until))
            ->where('status', '!=', 'cancelled')
            ->whereHas('order', fn ($query) => $query->whereNotIn('status', ['cancelled', 'expired']))
            ->get(['tenant_id', 'scheduled_at']);

        $booked = [];
        $size = self::SLOT_MINUTES * 60;
        foreach ($rows as $row) {
            $slot = intdiv($row->scheduled_at->getTimestamp(), $size) * $size;
            $key = $row->tenant_id.'|'.$slot;
            $booked[$key] = ($booked[$key] ?? 0) + 1;
        }

        return $booked;
    }

    private function local(CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone((string) config('app.display_timezone', 'Asia/Jakarta'));
    }
}
