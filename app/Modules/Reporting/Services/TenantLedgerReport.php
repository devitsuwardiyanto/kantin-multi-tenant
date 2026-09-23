<?php

namespace App\Modules\Reporting\Services;

use App\Models\LedgerEntry;
use App\Models\TenantBalance;
use Carbon\CarbonInterface;

/**
 * Laporan & rekonsiliasi keuangan tenant yang DITURUNKAN dari ledger append-only (sumber
 * kebenaran). Saldo materialisasi (tenant_balances) hanya cache; reconcile() membuktikan
 * cache == akumulasi ledger.
 */
final class TenantLedgerReport
{
    /**
     * Ringkasan pendapatan tenant dalam rentang (opsional).
     *
     * @return array{gross_sales: int, commission: int, net: int, withdrawn: int, entries: int}
     */
    public function summary(int $tenantId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $query = LedgerEntry::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->when($from !== null, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('created_at', '<=', $to));

        $gross = (int) (clone $query)->where('type', 'sale_credit')->sum('available_delta');
        $commission = (int) abs((int) (clone $query)->where('type', 'commission_debit')->sum('available_delta'));
        $withdrawn = (int) abs((int) (clone $query)->where('type', 'withdrawal_debit')->sum('held_delta'));

        return [
            'gross_sales' => $gross,
            'commission' => $commission,
            'net' => $gross - $commission,
            'withdrawn' => $withdrawn,
            'entries' => (int) (clone $query)->count(),
        ];
    }

    /**
     * Rekonsiliasi: akumulasi delta ledger vs saldo materialisasi.
     *
     * @return array{ledger_available: int, ledger_held: int, balance_available: int, balance_held: int, matches: bool}
     */
    public function reconcile(int $tenantId): array
    {
        $ledgerAvailable = (int) LedgerEntry::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->sum('available_delta');
        $ledgerHeld = (int) LedgerEntry::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->sum('held_delta');

        $balance = TenantBalance::query()->firstOrNew(['tenant_id' => $tenantId]);
        $balanceAvailable = (int) ($balance->available_amount ?? 0);
        $balanceHeld = (int) ($balance->held_amount ?? 0);

        return [
            'ledger_available' => $ledgerAvailable,
            'ledger_held' => $ledgerHeld,
            'balance_available' => $balanceAvailable,
            'balance_held' => $balanceHeld,
            'matches' => $ledgerAvailable === $balanceAvailable && $ledgerHeld === $balanceHeld,
        ];
    }

    /**
     * Baris ledger untuk ekspor CSV (terbaru dahulu). Nilai heterogen (skalar) per kolom.
     *
     * @return array<int, array{tanggal: string, tipe: string, available_delta: int, held_delta: int, order_id: int<0, max>|null, payment_id: int<0, max>|null, withdrawal_id: int<0, max>|null}>
     */
    public function ledgerRows(int $tenantId): array
    {
        return LedgerEntry::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (LedgerEntry $e): array => [
                'tanggal' => (string) $e->created_at?->toDateTimeString(),
                'tipe' => (string) $e->type,
                'available_delta' => (int) $e->available_delta,
                'held_delta' => (int) $e->held_delta,
                'order_id' => $e->order_id !== null ? (int) $e->order_id : null,
                'payment_id' => $e->payment_id !== null ? (int) $e->payment_id : null,
                'withdrawal_id' => $e->withdrawal_id !== null ? (int) $e->withdrawal_id : null,
            ])
            ->all();
    }
}
