<?php

namespace App\Modules\Payments\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\TenantOrder;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Payments\Events\PaymentVerified;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Settlement & reversal berbasis ledger APPEND-ONLY. Satu pembayaran dipecah per tenant
 * (split allocation): sale_credit (+subtotal) dan commission_debit (−komisi) → net ke saldo
 * available tenant. Saldo adalah materialisasi dari akumulasi delta ledger.
 *
 *  - Idempoten: idempotency_key ledger UNIQUE (insertOrIgnore); replay tak menggandakan efek.
 *  - Koreksi memakai entri reversal (bukan edit historis). CHECK non-negatif menjaga saldo.
 *  - Uang = integer Rupiah. Pajak & biaya layanan adalah bagian platform (di luar ledger tenant).
 *  - UC-10: pengelola kantin mendapat entri platform_ledger_entries (komisi, pajak, biaya
 *    layanan, selisih pembulatan); rincian pemecahan disimpan di payment_allocations. Total
 *    seluruh alokasi = nominal pembayaran (selisih 0) — bila tidak, transaksi di-rollback.
 *  - Baris payment dikunci selama settlement untuk mencegah alokasi ganda.
 */
class SettlePayment
{
    public function __construct(private AuditLogger $audit) {}

    public function settle(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $tenantOrders = TenantOrder::query()->withoutGlobalScope('tenant')->where('order_id', $payment->order_id)->orderBy('tenant_id')->get();
            $settled = false;

            foreach ($tenantOrders as $tenantOrder) {
                $inserted = $this->ledger($payment, $tenantOrder, 'sale_credit', (int) $tenantOrder->subtotal_amount, 'sale');
                if (! $inserted) {
                    continue; // sudah disettle sebelumnya (idempoten)
                }

                $settled = true;
                $this->ledger($payment, $tenantOrder, 'commission_debit', -((int) $tenantOrder->commission_amount), 'commission');
                $this->creditAvailable((int) $tenantOrder->tenant_id, (int) $tenantOrder->net_amount);

                $this->audit->record('ledger', $tenantOrder->id, 'settlement', null, [
                    'payment_id' => $payment->id,
                    'net' => $tenantOrder->net_amount,
                ], (int) $tenantOrder->tenant_id);
            }

            if ($settled) {
                $this->allocate($payment, $tenantOrders);
                event(new PaymentVerified($payment));
            }
        });
    }

    /**
     * UC-10 langkah 4 & 6 + alur 1a: kredit pengelola kantin (komisi + pajak + biaya layanan +
     * selisih pembulatan) dan rincian pemecahan. Invarian: Σ alokasi = nominal pembayaran.
     *
     * @param  Collection<int, TenantOrder>  $tenantOrders
     */
    private function allocate(Payment $payment, Collection $tenantOrders): void
    {
        $order = Order::query()->findOrFail($payment->order_id);
        $commission = (int) $tenantOrders->sum('commission_amount');
        $tax = (int) $tenantOrders->sum('tax_amount');
        $fee = (int) $tenantOrders->sum('service_fee_amount');
        $tenantNet = (int) $tenantOrders->sum('net_amount');
        $rounding = (int) $payment->amount - $tenantNet - $commission - $tax - $fee;

        foreach ($tenantOrders as $tenantOrder) {
            $this->allocation($payment, 'tenant:'.$tenantOrder->tenant_id, [
                'tenant_id' => $tenantOrder->tenant_id,
                'tenant_order_id' => $tenantOrder->id,
                'recipient' => 'tenant',
                'subtotal_amount' => $tenantOrder->subtotal_amount,
                'commission_rate_snapshot' => $tenantOrder->commission_rate_snapshot,
                'commission_amount' => $tenantOrder->commission_amount,
                'amount' => $tenantOrder->net_amount,
            ]);
        }

        $this->allocation($payment, 'canteen', [
            'recipient' => 'canteen',
            'commission_amount' => $commission,
            'tax_amount' => $tax,
            'service_fee_amount' => $fee,
            'rounding_amount' => $rounding,
            'amount' => $commission + $tax + $fee + $rounding,
        ]);

        foreach (['commission_credit' => $commission, 'tax_credit' => $tax, 'service_fee_credit' => $fee, 'rounding_credit' => $rounding] as $type => $amount) {
            if ($amount !== 0) {
                $this->platformLedger($payment, $order, $type, $amount);
            }
        }

        if ($rounding !== 0) {
            Log::info('Selisih pembulatan settlement dialokasikan ke pengelola kantin.', ['payment_id' => $payment->id, 'rounding' => $rounding]);
        }

        $allocated = (int) DB::table('payment_allocations')->where('payment_id', $payment->id)->sum('amount');
        if ($allocated !== (int) $payment->amount) {
            throw new LogicException("Alokasi {$allocated} ≠ nominal {$payment->amount} (payment {$payment->id}).");
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function allocation(Payment $payment, string $recipientKey, array $values): void
    {
        DB::table('payment_allocations')->insertOrIgnore([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'recipient_key' => $recipientKey,
            'created_at' => now(),
            'updated_at' => now(),
        ] + $values);
    }

    private function platformLedger(Payment $payment, Order $order, string $type, int $amount, string $prefix = 'settle'): void
    {
        DB::table('platform_ledger_entries')->insertOrIgnore([
            'canteen_id' => $order->canteen_id,
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'idempotency_key' => $prefix.':platform:'.$payment->id.':'.$type,
            'type' => $prefix === 'settle' ? $type : 'reversal',
            'amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

            // Kredit pengelola kantin ikut dibalik (entri reversal, bukan hapus).
            $order = Order::query()->findOrFail($payment->order_id);
            $platform = DB::table('platform_ledger_entries')->where('payment_id', $payment->id)->where('type', '!=', 'reversal')->get(['type', 'amount']);
            foreach ($platform as $entry) {
                $this->platformLedger($payment, $order, (string) $entry->type, -((int) $entry->amount), 'reverse');
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
