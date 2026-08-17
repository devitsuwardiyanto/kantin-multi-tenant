<?php

namespace App\Modules\Payments\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentChargeRequest;
use App\Modules\Payments\Exceptions\PaymentException;
use Illuminate\Database\UniqueConstraintViolationException;
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
 */
final class PaymentService
{
    public function __construct(
        private PaymentGateway $gateway,
        private AuditLogger $audit,
    ) {}

    /**
     * Membuat/mengembalikan tagihan QRIS untuk order yang menunggu pembayaran.
     *
     * @throws PaymentException
     */
    public function initiate(Order $order): Payment
    {
        $existing = Payment::query()->where('order_id', $order->id)->first();
        if ($existing !== null) {
            return $existing->load('latestAttempt');
        }

        if (! $order->isAwaitingPayment()) {
            throw PaymentException::orderNotPayable();
        }

        try {
            return DB::transaction(function () use ($order): Payment {
                $reference = 'PAY-'.strtoupper(Str::random(16));

                $payment = new Payment;
                $payment->forceFill([
                    'order_id' => $order->id,
                    'payment_reference' => $reference,
                    'idempotency_key' => 'pay-init-'.$order->id,
                    'amount' => $order->grand_total_amount,
                    'status' => 'pending',
                ])->save();

                $charge = $this->gateway->createQrisCharge(new PaymentChargeRequest(
                    reference: $reference,
                    orderNumber: $order->order_number,
                    amount: (int) $order->grand_total_amount,
                ));

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
                ], null, $order->canteen_id);

                return $payment->setRelation('latestAttempt', $attempt);
            });
        } catch (UniqueConstraintViolationException) {
            // Balapan: payment sudah dibuat proses lain.
            return Payment::query()->where('order_id', $order->id)->firstOrFail()->load('latestAttempt');
        }
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
