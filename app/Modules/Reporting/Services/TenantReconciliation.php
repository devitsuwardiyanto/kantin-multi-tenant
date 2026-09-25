<?php

namespace App\Modules\Reporting\Services;

use App\Models\Withdrawal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * UC-18 Lihat Rekonsiliasi Bagi Hasil: rincian pendapatan per periode (bulan, WIB) yang
 * DITURUNKAN dari ledger append-only, entri per transaksi beserta referensi pembayaran, dan
 * pembayaran "perlu peninjauan" (alur 3a) yang ditandai khusus dan tidak dihitung ke saldo.
 */
final class TenantReconciliation
{
    private const WITHDRAWAL_LABELS = [
        'hold' => 'Penarikan dana (ditahan)',
        'release' => 'Penarikan ditolak — dana kembali',
        'withdrawal_debit' => 'Penarikan dicairkan',
    ];

    /**
     * @return array{
     *     gross: int, commission: int, commission_rate: float|null, other: int, net: int,
     *     available: int, held: int, ledger_available: int, ledger_entries: int, matches: bool,
     *     rows: list<array{at: string, reference: string, kind: string, label: string, order_id: int|null, order_number: string|null, gross: int, commission: int, other: int, net: int, held: int, refund_pending: bool}>,
     *     review: list<array{at: string, reference: string, order_id: int, order_number: string, gross: int}>
     * }
     */
    public function period(int $tenantId, CarbonImmutable $month): array
    {
        [$start, $end] = $this->bounds($month);

        $entries = DB::table('ledger_entries')
            ->leftJoin('payments', 'payments.id', '=', 'ledger_entries.payment_id')
            ->leftJoin('orders', 'orders.id', '=', 'ledger_entries.order_id')
            ->where('ledger_entries.tenant_id', $tenantId)
            ->whereBetween('ledger_entries.created_at', [$start, $end])
            ->orderByDesc('ledger_entries.id')
            ->get(['ledger_entries.*', 'payments.payment_reference', 'orders.order_number']);

        $refundPending = DB::table('tenant_orders')->where('tenant_id', $tenantId)->where('refund_status', 'pending')->pluck('order_id')->all();

        $rows = [];
        foreach ($entries->whereNotNull('payment_id')->groupBy('payment_id') as $group) {
            $first = $group->last();
            $gross = (int) $group->where('type', 'sale_credit')->sum('available_delta');
            $commission = (int) -$group->where('type', 'commission_debit')->sum('available_delta');
            $other = (int) -$group->where('type', 'reversal')->sum('available_delta');
            $rows[] = [
                'at' => (string) $first->created_at,
                'reference' => (string) $first->payment_reference,
                'kind' => 'sale',
                'label' => $other > 0 ? 'Penjualan (dibalik/refund)' : 'Penjualan',
                'order_id' => (int) $first->order_id,
                'order_number' => (string) $first->order_number,
                'gross' => $gross,
                'commission' => $commission,
                'other' => $other,
                'net' => $gross - $commission - $other,
                'held' => 0,
                'refund_pending' => in_array((int) $first->order_id, $refundPending, true),
            ];
        }

        foreach ($entries->whereNotNull('withdrawal_id') as $entry) {
            $rows[] = [
                'at' => (string) $entry->created_at,
                'reference' => Withdrawal::referenceFor((int) $entry->withdrawal_id, (string) $entry->created_at),
                'kind' => 'withdrawal',
                'label' => self::WITHDRAWAL_LABELS[$entry->type] ?? (string) $entry->type,
                'order_id' => null,
                'order_number' => null,
                'gross' => 0,
                'commission' => 0,
                'other' => 0,
                'net' => (int) $entry->available_delta,
                'held' => (int) $entry->held_delta,
                'refund_pending' => false,
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($b['at'], $a['at']));

        $sales = array_filter($rows, fn (array $r): bool => $r['kind'] === 'sale');
        $gross = array_sum(array_column($sales, 'gross'));
        $commission = array_sum(array_column($sales, 'commission'));
        $other = array_sum(array_column($sales, 'other'));

        $rates = DB::table('tenant_orders')
            ->whereIn('order_id', array_column($sales, 'order_id'))
            ->where('tenant_id', $tenantId)
            ->distinct()
            ->pluck('commission_rate_snapshot');

        $ledgerAvailable = (int) DB::table('ledger_entries')->where('tenant_id', $tenantId)->sum('available_delta');
        $balance = DB::table('tenant_balances')->where('tenant_id', $tenantId)->first();
        $available = (int) ($balance->available_amount ?? 0);

        return [
            'gross' => $gross,
            'commission' => $commission,
            'commission_rate' => $rates->count() === 1 ? (float) $rates->first() * 100 : null,
            'other' => $other,
            'net' => $gross - $commission - $other,
            'available' => $available,
            'held' => (int) ($balance->held_amount ?? 0),
            'ledger_available' => $ledgerAvailable,
            'ledger_entries' => (int) DB::table('ledger_entries')->where('tenant_id', $tenantId)->count(),
            'matches' => $ledgerAvailable === $available,
            'rows' => array_map(fn (array $r): array => ['at' => $this->local($r['at'])] + $r, $rows),
            'review' => $this->needsReview($tenantId, $start, $end),
        ];
    }

    /**
     * Langkah 4: rincian pesanan asal sebuah entri — HANYA item milik tenant ini.
     *
     * @return array{order_number: string, placed_at: string, status: string, items: list<array{name: string, quantity: int, line_total: int, note: string|null}>, subtotal: int, commission: int, net: int}|null
     */
    public function orderDetail(int $tenantId, int $orderId): ?array
    {
        $tenantOrder = DB::table('tenant_orders')
            ->join('orders', 'orders.id', '=', 'tenant_orders.order_id')
            ->where('tenant_orders.tenant_id', $tenantId)
            ->where('tenant_orders.order_id', $orderId)
            ->first(['tenant_orders.*', 'orders.order_number', 'orders.placed_at']);

        if ($tenantOrder === null) {
            return null;
        }

        $items = DB::table('order_items')
            ->where('tenant_order_id', $tenantOrder->id)
            ->orderBy('id')
            ->get(['name_snapshot', 'quantity', 'line_total', 'note'])
            ->map(fn (object $i): array => ['name' => (string) $i->name_snapshot, 'quantity' => (int) $i->quantity, 'line_total' => (int) $i->line_total, 'note' => $i->note])
            ->all();

        return [
            'order_number' => (string) $tenantOrder->order_number,
            'placed_at' => $this->local((string) $tenantOrder->placed_at),
            'status' => (string) $tenantOrder->status,
            'items' => array_values($items),
            'subtotal' => (int) $tenantOrder->subtotal_amount,
            'commission' => (int) $tenantOrder->commission_amount,
            'net' => (int) $tenantOrder->net_amount,
        ];
    }

    /**
     * Alur 3a: pembayaran berstatus needs_review yang memuat sub-pesanan tenant ini.
     *
     * @return list<array{at: string, reference: string, order_id: int, order_number: string, gross: int}>
     */
    private function needsReview(int $tenantId, string $start, string $end): array
    {
        return array_values(DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->join('tenant_orders', 'tenant_orders.order_id', '=', 'orders.id')
            ->where('tenant_orders.tenant_id', $tenantId)
            ->where('payments.status', 'needs_review')
            ->whereBetween('payments.updated_at', [$start, $end])
            ->orderByDesc('payments.id')
            ->get(['payments.updated_at', 'payments.payment_reference', 'orders.id as order_id', 'orders.order_number', 'tenant_orders.subtotal_amount'])
            ->map(fn (object $p): array => [
                'at' => $this->local((string) $p->updated_at),
                'reference' => (string) $p->payment_reference,
                'order_id' => (int) $p->order_id,
                'order_number' => (string) $p->order_number,
                'gross' => (int) $p->subtotal_amount,
            ])
            ->all());
    }

    /** @return array{0: string, 1: string} batas bulan WIB dalam zona aplikasi (UTC) */
    private function bounds(CarbonImmutable $month): array
    {
        $zone = (string) config('app.display_timezone');
        $local = $month->shiftTimezone($zone);

        return [
            $local->startOfMonth()->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s.u'),
            $local->endOfMonth()->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s.u'),
        ];
    }

    private function local(string $utc): string
    {
        return CarbonImmutable::parse($utc, (string) config('app.timezone'))->setTimezone((string) config('app.display_timezone'))->format('Y-m-d H:i');
    }
}
