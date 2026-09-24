<?php

namespace App\Modules\Payments\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\TenantOrder;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Ordering\Services\CancelUnpaidOrder;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentChargeRequest;
use App\Modules\Payments\Data\QrisCharge;
use App\Modules\Payments\Exceptions\GatewayUnavailableException;
use App\Modules\Payments\Exceptions\PaymentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orkestrasi pembayaran QRIS (bebas provider via PaymentGateway). Modul 10 = inisiasi tagihan
 * dinamis + transisi status. Kredit saldo tenant (settlement/ledger) MENYUSUL Modul 11.
 *
 *  - Idempoten: satu payment per order (UNIQUE order_id + idempotency_key). Inisiasi berulang
 *    mengembalikan payment yang sama (tanpa tagihan dobel).
 *  - Konfirmasi sandbox menirukan callback provider sukses (Modul 11 menggantikannya dengan
 *    webhook ber-signature + ledger). Idempoten via status + provider_event_id UNIQUE.
 *  - UC-07: permintaan charge membawa rincian split per tenant; gateway dipanggil DI LUAR
 *    transaksi basis data dengan maksimal 3 percobaan (alur 1a); QRIS kedaluwarsa ditandai
 *    expired lalu dapat dibangkitkan ulang (alur 4a); masa berlaku maksimal 15 menit.
 */
final class PaymentService
{
    /** UC-07 alur 1a: percobaan maksimal ke gateway sebelum menampilkan pesan gangguan. */
    public const MAX_GATEWAY_TRIES = 3;

    /** UC-07 kualitas: masa berlaku QRIS maksimal 15 menit. */
    public const MAX_EXPIRY_SECONDS = 900;

    public function __construct(
        private PaymentGateway $gateway,
        private AuditLogger $audit,
    ) {}

    /**
     * Membuat/mengembalikan tagihan QRIS untuk order yang menunggu pembayaran. QRIS aktif yang
     * belum kedaluwarsa dikembalikan apa adanya (tanpa tagihan dobel); QRIS kedaluwarsa ditandai
     * expired lalu diganti tagihan baru.
     *
     * @throws PaymentException
     */
    public function initiate(Order $order): Payment
    {
        $payment = Payment::query()->where('order_id', $order->id)->first();
        if ($payment === null) {
            if (! $order->isAwaitingPayment()) {
                throw PaymentException::orderNotPayable();
            }
            $payment = $this->createPayment($order);
        }

        if ($payment->isPaid() || ! $order->isAwaitingPayment()) {
            return $payment->load('latestAttempt');
        }

        // Satu pembangkitan QRIS per payment pada satu waktu (klik ganda / dua tab).
        return Cache::lock('payment-charge:'.$payment->id, 15)->block(10, function () use ($order, $payment): Payment {
            $attempt = $payment->latestAttempt()->first();
            if ($attempt !== null && $attempt->status === 'pending' && ! $attempt->isExpired()) {
                return $payment->setRelation('latestAttempt', $attempt);
            }

            if ($attempt !== null && $attempt->status === 'pending') {
                $attempt->forceFill(['status' => 'expired'])->save();
                $this->audit->record('payment', $payment->id, 'qris_expired', null, ['attempt_id' => $attempt->id], null, $order->canteen_id);
            }

            return $payment->setRelation('latestAttempt', $this->charge($order, $payment));
        });
    }

    /**
     * Batalkan pesanan yang belum dibayar: QRIS aktif kedaluwarsa, payment gagal, lalu order
     * dibatalkan dan stok dikembalikan oleh modul Ordering.
     */
    public function cancelUnpaid(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $payment = Payment::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($payment !== null && ! $payment->isPaid()) {
                PaymentAttempt::query()->where('payment_id', $payment->id)->where('status', 'pending')->update(['status' => 'expired']);
                $payment->forceFill(['status' => 'failed'])->save();
            }

            app(CancelUnpaidOrder::class)->cancel($order);
        });
    }

    private function createPayment(Order $order): Payment
    {
        try {
            return DB::transaction(function () use ($order): Payment {
                $payment = new Payment;
                $payment->forceFill([
                    'order_id' => $order->id,
                    'payment_reference' => 'PAY-'.strtoupper(Str::random(16)),
                    'idempotency_key' => 'pay-init-'.$order->id,
                    'amount' => $order->grand_total_amount,
                    'status' => 'pending',
                ])->save();

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            // Balapan: payment sudah dibuat proses lain.
            return Payment::query()->where('order_id', $order->id)->firstOrFail();
        }
    }

    /**
     * Panggil gateway (maks. 3 percobaan, di luar transaksi) lalu simpan attempt QRIS.
     *
     * @throws PaymentException
     */
    private function charge(Order $order, Payment $payment): PaymentAttempt
    {
        $request = new PaymentChargeRequest(
            reference: $payment->payment_reference,
            orderNumber: $order->order_number,
            amount: (int) $payment->amount,
            expiresInSeconds: min(self::MAX_EXPIRY_SECONDS, (int) config('services.qris.expiry_seconds', self::MAX_EXPIRY_SECONDS)),
            splits: $this->splits($order),
        );

        try {
            /** @var QrisCharge $charge */
            $charge = retry(self::MAX_GATEWAY_TRIES, fn (): QrisCharge => $this->gateway->createQrisCharge($request), 200,
                fn (\Throwable $e): bool => $e instanceof GatewayUnavailableException);
        } catch (GatewayUnavailableException) {
            $this->audit->record('payment', $payment->id, 'gateway_unavailable', null, [
                'tries' => self::MAX_GATEWAY_TRIES,
                'provider' => $this->gateway->name(),
            ], null, $order->canteen_id);

            throw PaymentException::gatewayUnavailable();
        }

        $attempt = new PaymentAttempt;
        $attempt->forceFill([
            'payment_id' => $payment->id,
            'provider_reference' => $charge->providerReference,
            'qris_payload' => $charge->qrisPayload,
            'status' => 'pending',
            'expires_at' => $charge->expiresAt,
        ])->save();

        $this->audit->record('payment', $payment->id, 'initiated', null, [
            'order_id' => $order->id,
            'amount' => $payment->amount,
            'provider' => $this->gateway->name(),
            'splits' => $request->splits,
        ], null, $order->canteen_id);

        return $attempt;
    }

    /**
     * Rincian split per tenant (UC-07 langkah 1): gross = subtotal + pajak + biaya layanan
     * tenant tersebut; jumlah gross seluruh tenant = total order.
     *
     * @return list<array{tenant_id: int, gross: int, commission: int, net: int}>
     */
    private function splits(Order $order): array
    {
        $splits = [];
        foreach (TenantOrder::query()->withoutGlobalScope('tenant')->where('order_id', $order->id)->orderBy('tenant_id')->get() as $tenantOrder) {
            $splits[] = [
                'tenant_id' => $tenantOrder->tenant_id,
                'gross' => $tenantOrder->subtotal_amount + $tenantOrder->tax_amount + $tenantOrder->service_fee_amount,
                'commission' => $tenantOrder->commission_amount,
                'net' => $tenantOrder->net_amount,
            ];
        }

        return $splits;
    }

    /**
     * Simulasi pembayaran sukses (SANDBOX): tandai attempt/payment/order lunas dan catat
     * payment_event append-only. Idempoten. Bukan pengganti webhook produksi (Modul 11).
     *
     * @throws PaymentException
     */
    public function confirmSandbox(Payment $payment): Payment
    {
        if (! $this->gateway->isSandbox()) {
            throw PaymentException::sandboxOnly();
        }

        $payment->refresh();
        if ($payment->isPaid()) {
            return $payment;
        }

        $attempt = $payment->latestAttempt()->first();
        if ($attempt !== null && $attempt->isExpired()) {
            throw PaymentException::attemptExpired();
        }

        DB::transaction(function () use ($payment, $attempt): void {
            if ($attempt !== null) {
                $attempt->forceFill(['status' => 'success'])->save();
            }

            $payment->forceFill(['status' => 'paid', 'settled_at' => now()])->save();

            Order::query()->whereKey($payment->order_id)->update(['status' => 'paid']);

            DB::table('payment_events')->insert([
                'payment_id' => $payment->id,
                'payment_attempt_id' => $attempt?->id,
                'provider_event_id' => 'SBX-'.$payment->payment_reference,
                'signature' => null,
                'payload' => json_encode(['simulated' => true, 'amount' => $payment->amount], JSON_THROW_ON_ERROR),
                'result' => 'verified',
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->record('payment', $payment->id, 'paid_sandbox', null, [
                'amount' => $payment->amount,
            ], null, null);
        });

        return $payment;
    }
}
