<?php

namespace App\Modules\Payments\Services;

use App\Events\NewTenantOrderReceived;
use App\Models\Payment;
use App\Models\TenantOrder;
use App\Modules\Admin\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Settlement & reversal berbasis ledger APPEND-ONLY. Satu pembayaran dipecah per tenant
 * (split allocation): sale_credit (+subtotal) dan commission_debit (−komisi) → net ke saldo
 * available tenant. Saldo adalah materialisasi dari akumulasi delta ledger.
 *
 *  - Idempoten: idempotency_key ledger UNIQUE (insertOrIgnore); replay tak menggandakan efek.
 *  - Koreksi memakai entri reversal (bukan edit historis). CHECK non-negatif menjaga saldo.
 *  - Uang = integer Rupiah. Pajak & biaya layanan adalah bagian platform (di luar ledger tenant).
 */
final class SettlePayment
{
    public function __construct(private AuditLogger $audit) {}

    public function settle(Payment $payment): void
    {
        $tenantOrders = TenantOrder::query()->where('order_id', $payment->order_id)->get();

        DB::transaction(function () use ($payment, $tenantOrders): void {
            foreach ($tenantOrders as $tenantOrder) {
                $inserted = $this->ledger($payment, $tenantOrder, 'sale_credit', (int) $tenantOrder->subtotal_amount, 'sale');
                if (! $inserted) {
                    continue; // sudah disettle sebelumnya (idempoten)
                }

                $this->ledger($payment, $tenantOrder, 'commission_debit', -((int) $tenantOrder->commission_amount), 'commission');
                $this->creditAvailable((int) $tenantOrder->tenant_id, (int) $tenantOrder->net_amount);

                $this->audit->record('ledger', $tenantOrder->id, 'settlement', null, [
                    'payment_id' => $payment->id,
                    'net' => $tenantOrder->net_amount,
                ], (int) $tenantOrder->tenant_id);

                // Pesanan lunas → masuk antrean dapur (disiarkan setelah commit).
                event(new NewTenantOrderReceived($tenantOrder));
            }
        });
    }

    /**
     * Membalik settlement sebuah pembayaran (mis. refund) memakai entri reversal. CHECK saldo
     * menolak bila dana sudah tak mencukupi (mis. telah ditarik) — gagal-tertutup.
     */
    public function reverse(Payment $payment): void
    {
        $tenantOrders = TenantOrder::query()->where('order_id', $payment->order_id)->get();

        DB::transaction(function () use ($payment, $tenantOrders): void {
            foreach ($tenantOrders as $tenantOrder) {
                $inserted = $this->ledger($payment, $tenantOrder, 'reversal', -((int) $tenantOrder->net_amount), 'reverse');
                if (! $inserted) {
                    continue;
                }

                $this->creditAvailable((int) $tenantOrder->tenant_id, -((int) $tenantOrder->net_amount));

                $this->audit->record('ledger', $tenantOrder->id, 'reversal', null, [
                    'payment_id' => $payment->id,
                    'net' => -$tenantOrder->net_amount,
                ], (int) $tenantOrder->tenant_id);
            }

            $payment->forceFill(['status' => 'refunded'])->save();
        });
    }

    /**
     * Menyisipkan satu entri ledger idempoten. Mengembalikan true jika benar-benar tersisip.
     */
    private function ledger(Payment $payment, TenantOrder $tenantOrder, string $type, int $availableDelta, string $suffix): bool
    {
        $affected = DB::table('ledger_entries')->insertOrIgnore([
            'tenant_id' => $tenantOrder->tenant_id,
            'order_id' => $tenantOrder->order_id,
            'payment_id' => $payment->id,
            'withdrawal_id' => null,
            'idempotency_key' => 'settle:'.$payment->id.':'.$tenantOrder->id.':'.$suffix,
            'type' => $type,
            'available_delta' => $availableDelta,
            'held_delta' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $affected > 0;
    }

    private function creditAvailable(int $tenantId, int $availableDelta): void
    {
        // Pastikan baris saldo ada (tenant_id bukan kolom fillable Eloquent) lalu update atomik.
        DB::table('tenant_balances')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'available_amount' => 0,
            'held_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('tenant_balances')->where('tenant_id', $tenantId);
        if ($availableDelta >= 0) {
            $row->increment('available_amount', $availableDelta, ['updated_at' => now()]);
        } else {
            $row->decrement('available_amount', -$availableDelta, ['updated_at' => now()]);
        }
    }
}
