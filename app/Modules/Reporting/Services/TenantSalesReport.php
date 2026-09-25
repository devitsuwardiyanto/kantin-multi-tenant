<?php

namespace App\Modules\Reporting\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * UC-16 Lihat Laporan Penjualan: agregasi penjualan SATU tenant (tenant_id eksplisit, bukan
 * input klien) pada rentang tanggal kalender zona tampilan (WIB). Penjualan = sub-pesanan yang
 * pembayarannya sudah lunas (payments.paid) dan tidak dibatalkan dapur; waktu = settled_at.
 */
final class TenantSalesReport
{
    /** Status tenant_order yang tidak dihitung sebagai penjualan. */
    private const EXCLUDED_STATUSES = ['cancelled'];

    /**
     * @return array{
     *     revenue: int, transactions: int, average: int, median: int, days: int, per_day: float,
     *     change_percent: float|null,
     *     top_menus: list<array{name: string, quantity: int}>,
     *     hourly: array<int, int>,
     *     busy: array{from: int, to: int, share: int}|null,
     *     latest_sale_date: string|null
     * }
     */
    public function summary(int $tenantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sales = $this->sales($tenantId, $from, $to)->get(['tenant_orders.id', 'tenant_orders.subtotal_amount', 'payments.settled_at']);
        $revenue = (int) $sales->sum('subtotal_amount');
        $count = $sales->count();
        $days = (int) $from->diffInDays($to) + 1;

        $previousFrom = $from->subDays($days);
        $previousRevenue = (int) $this->sales($tenantId, $previousFrom, $from->subDay())->sum('tenant_orders.subtotal_amount');

        $hourly = $this->hourly($sales);

        return [
            'revenue' => $revenue,
            'transactions' => $count,
            'average' => $count > 0 ? intdiv($revenue, $count) : 0,
            'median' => $this->median($sales->pluck('subtotal_amount')->map(fn ($v): int => (int) $v)),
            'days' => $days,
            'per_day' => $days > 0 ? round($count / $days, 1) : 0.0,
            'change_percent' => $previousRevenue > 0 ? round(($revenue - $previousRevenue) / $previousRevenue * 100, 1) : null,
            'top_menus' => $this->topMenus(array_values($sales->pluck('id')->map(fn ($id): int => (int) $id)->all())),
            'hourly' => $hourly,
            'busy' => $this->busyWindow($hourly, $count),
            'latest_sale_date' => $count === 0 ? $this->latestSaleDate($tenantId) : null,
        ];
    }

    /**
     * Baris transaksi untuk ekspor (UC-17): satu baris per sub-pesanan, terlama dahulu.
     *
     * @return list<array{paid_at: string, order_number: string, items: string, subtotal: int, commission: int, net: int}>
     */
    public function rows(int $tenantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sales = $this->sales($tenantId, $from, $to)
            ->orderBy('payments.settled_at')
            ->get(['tenant_orders.id', 'orders.order_number', 'tenant_orders.subtotal_amount', 'tenant_orders.commission_amount', 'tenant_orders.net_amount', 'payments.settled_at']);

        $items = DB::table('order_items')
            ->whereIn('tenant_order_id', $sales->pluck('id')->all())
            ->orderBy('id')
            ->get(['tenant_order_id', 'name_snapshot', 'quantity'])
            ->groupBy('tenant_order_id');

        return array_values($sales->map(fn (\stdClass $sale): array => [
            'paid_at' => $this->local((string) $sale->settled_at)->format('Y-m-d H:i'),
            'order_number' => (string) $sale->order_number,
            'items' => $items->get($sale->id, collect())->map(fn (object $i): string => $i->quantity.'× '.$i->name_snapshot)->implode(', '),
            'subtotal' => (int) $sale->subtotal_amount,
            'commission' => (int) $sale->commission_amount,
            'net' => (int) $sale->net_amount,
        ])->all());
    }

    public function count(int $tenantId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->sales($tenantId, $from, $to)->count();
    }

    private function sales(int $tenantId, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $zone = (string) config('app.display_timezone');
        $start = $from->shiftTimezone($zone)->startOfDay()->setTimezone((string) config('app.timezone'));
        $end = $to->shiftTimezone($zone)->endOfDay()->setTimezone((string) config('app.timezone'));

        return DB::table('tenant_orders')
            ->join('orders', 'orders.id', '=', 'tenant_orders.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('tenant_orders.tenant_id', $tenantId)
            ->where('payments.status', 'paid')
            ->whereNotIn('tenant_orders.status', self::EXCLUDED_STATUSES)
            ->whereBetween('payments.settled_at', [$start->format('Y-m-d H:i:s.u'), $end->format('Y-m-d H:i:s.u')]);
    }

    /**
     * @param  Collection<int, \stdClass>  $sales
     * @return array<int, int> jam (0–23, WIB) → jumlah pesanan; rentang jam yang memiliki data
     */
    private function hourly(Collection $sales): array
    {
        $counts = [];
        foreach ($sales as $sale) {
            $hour = (int) $this->local((string) $sale->settled_at)->format('G');
            $counts[$hour] = ($counts[$hour] ?? 0) + 1;
        }

        if ($counts === []) {
            return [];
        }

        $hours = [];
        foreach (range(min(array_keys($counts)), max(array_keys($counts))) as $hour) {
            $hours[$hour] = $counts[$hour] ?? 0;
        }

        return $hours;
    }

    /**
     * Jendela tiga jam berurutan dengan pesanan terbanyak (mis. 11.00–13.00).
     *
     * @param  array<int, int>  $hourly
     * @return array{from: int, to: int, share: int}|null
     */
    private function busyWindow(array $hourly, int $total): ?array
    {
        if ($total === 0) {
            return null;
        }

        $best = null;
        foreach (array_keys($hourly) as $hour) {
            $sum = $hourly[$hour] + ($hourly[$hour + 1] ?? 0) + ($hourly[$hour + 2] ?? 0);
            if ($best === null || $sum > $best['sum']) {
                $last = isset($hourly[$hour + 2]) ? $hour + 2 : (isset($hourly[$hour + 1]) ? $hour + 1 : $hour);
                $best = ['from' => $hour, 'to' => $last, 'sum' => $sum];
            }
        }

        return ['from' => $best['from'], 'to' => $best['to'], 'share' => (int) round($best['sum'] / $total * 100)];
    }

    /**
     * @param  list<int>  $tenantOrderIds
     * @return list<array{name: string, quantity: int}>
     */
    private function topMenus(array $tenantOrderIds, int $limit = 5): array
    {
        if ($tenantOrderIds === []) {
            return [];
        }

        return array_values(DB::table('order_items')
            ->whereIn('tenant_order_id', $tenantOrderIds)
            ->groupBy('name_snapshot')
            ->orderByDesc(DB::raw('SUM(quantity)'))
            ->orderBy('name_snapshot')
            ->limit($limit)
            ->get(['name_snapshot', DB::raw('SUM(quantity) as qty')])
            ->map(fn (\stdClass $row): array => ['name' => (string) $row->name_snapshot, 'quantity' => (int) $row->qty])
            ->all());
    }

    /** @param  Collection<int, int>  $values */
    private function median(Collection $values): int
    {
        if ($values->isEmpty()) {
            return 0;
        }

        $sorted = $values->sort()->values();
        $middle = intdiv($sorted->count(), 2);

        return $sorted->count() % 2 === 1
            ? (int) $sorted[$middle]
            : intdiv((int) $sorted[$middle - 1] + (int) $sorted[$middle], 2);
    }

    private function latestSaleDate(int $tenantId): ?string
    {
        $latest = DB::table('tenant_orders')
            ->join('payments', 'payments.order_id', '=', 'tenant_orders.order_id')
            ->where('tenant_orders.tenant_id', $tenantId)
            ->where('payments.status', 'paid')
            ->whereNotIn('tenant_orders.status', self::EXCLUDED_STATUSES)
            ->max('payments.settled_at');

        return $latest === null ? null : $this->local((string) $latest)->toDateString();
    }

    private function local(string $utc): CarbonImmutable
    {
        return CarbonImmutable::parse($utc, (string) config('app.timezone'))->setTimezone((string) config('app.display_timezone'));
    }
}
