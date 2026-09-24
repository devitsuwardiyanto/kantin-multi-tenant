<?php

namespace App\Modules\Payments\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Ordering\Exceptions\CheckoutException;
use App\Modules\Payments\Support\WebhookSignatureVerifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * UC-08 Verifikasi Pembayaran: memproses webhook provider. Urutan pertahanan:
 *
 *  1. Verifikasi signature atas RAW BODY (HMAC) — invalid → 401 + audit, tanpa efek lain (2a).
 *  2. Payload disimpan sebagai payment_event append-only; provider_event_id UNIQUE → replay
 *     dijawab 200 duplicate (1a).
 *  3. Nominal dicocokkan dengan payment; tidak cocok → ditandai `needs_review` (3a).
 *  4. Settlement: baris payment + order dikunci, status "dibayar" tepat satu kali, lalu dana
 *     dipecah (UC-10) dalam SATU transaksi. Gagal → rollback, payment `settlement_failed`,
 *     500 agar provider mengirim ulang; `payments:reprocess-settlements` memproses ulang.
 *  5. Status expire/deny → pesanan dibatalkan dan stok dikembalikan (4a).
 *
 * @phpstan-type WebhookResult array{status: string, code: int}
 */
final class ProcessPaymentWebhook
{
    private const PAID = ['success', 'settlement'];

    private const FAILED = ['expire', 'expired', 'deny', 'denied', 'failure'];

    public function __construct(
        private WebhookSignatureVerifier $verifier,
        private SettlePayment $settle,
        private AuditLogger $audit,
    ) {}

    /**
     * @return WebhookResult
     */
    public function handle(string $rawBody, ?string $signature): array
    {
        if (! $this->verifier->verify($rawBody, $signature)) {
            $this->audit->record('payment_webhook', null, 'invalid_signature', null, ['body_sha256' => hash('sha256', $rawBody)]);

            return ['status' => 'invalid_signature', 'code' => 401];
        }

        $data = json_decode($rawBody, true);
        if (! is_array($data)) {
            return ['status' => 'bad_request', 'code' => 400];
        }

        $eventId = $data['event_id'] ?? null;
        $reference = $data['payment_reference'] ?? null;
        $status = $data['status'] ?? null;
        $amount = $data['amount'] ?? null;
        if (! is_string($eventId) || ! is_string($reference) || ! is_string($status)) {
            return ['status' => 'bad_request', 'code' => 400];
        }
        if (in_array($status, self::PAID, true) && ! is_int($amount)) {
            return ['status' => 'bad_request', 'code' => 400];
        }

        $payment = Payment::query()->where('payment_reference', $reference)->first();
        if ($payment === null) {
            return ['status' => 'not_found', 'code' => 404];
        }

        $existing = PaymentEvent::query()->where('provider_event_id', $eventId)->first();
        if ($existing !== null) {
            // Kiriman ulang provider setelah settlement gagal diproses ulang; selain itu duplikat.
            return $payment->status === 'settlement_failed' && $existing->result === 'verified'
                ? $this->markPaid($payment, $existing)
                : ['status' => 'duplicate', 'code' => 200];
        }

        $result = match (true) {
            in_array($status, self::PAID, true) && $amount !== (int) $payment->amount => 'amount_mismatch',
            in_array($status, self::PAID, true) => 'verified',
            in_array($status, self::FAILED, true) => 'cancelled',
            default => 'ignored',
        };

        try {
            $event = new PaymentEvent;
            $event->forceFill([
                'payment_id' => $payment->id,
                'payment_attempt_id' => $payment->latestAttempt()->value('id'),
                'provider_event_id' => $eventId,
                'signature' => $signature,
                'payload' => $data,
                'result' => $result,
                'received_at' => now(),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            return ['status' => 'duplicate', 'code' => 200];
        }

        return match ($result) {
            'verified' => $this->markPaid($payment, $event),
            'amount_mismatch' => $this->flagForReview($payment, 'amount_mismatch', ['expected' => (int) $payment->amount, 'received' => $amount]),
            'cancelled' => $this->cancel($payment),
            default => ['status' => 'ignored', 'code' => 200],
        };
    }

    /**
     * UC-10 alur 3a: proses ulang pembayaran `settlement_failed` dari event terverifikasi terakhir.
     */
    public function reprocess(int $paymentId): bool
    {
        $payment = Payment::query()->find($paymentId);
        $event = PaymentEvent::query()->where('payment_id', $paymentId)->where('result', 'verified')->latest('id')->first();
        if ($payment === null || $event === null) {
            return false;
        }

        return $this->markPaid($payment, $event)['code'] === 200;
    }

    /**
     * @return WebhookResult
     */
    private function markPaid(Payment $payment, PaymentEvent $event): array
    {
        try {
            $outcome = DB::transaction(function () use ($payment, $event): string {
                $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
                if ($locked->isPaid()) {
                    return 'ok'; // tepat satu kali
                }
                if ($order->status !== 'awaiting_payment') {
                    // Mis. pesanan sudah dibatalkan pelanggan: dana masuk harus ditinjau (refund).
                    $locked->forceFill(['status' => 'needs_review'])->save();
                    $this->audit->record('payment', $locked->id, 'paid_after_cancel', null, ['order_status' => $order->status], null, $order->canteen_id);

                    return 'needs_review';
                }

                if ($event->payment_attempt_id !== null) {
                    PaymentAttempt::query()->whereKey($event->payment_attempt_id)->update(['status' => 'success']);
                }
                $locked->forceFill(['status' => 'paid', 'settled_at' => now()])->save();
                $order->forceFill(['status' => 'paid'])->save();

                $this->settle->settle($locked);

                return 'ok';
            });
        } catch (Throwable $e) {
            // UC-10 alur 3a: seluruh entri sudah di-rollback; tandai untuk pemrosesan ulang.
            Payment::query()->whereKey($payment->id)->update(['status' => 'settlement_failed']);
            $this->audit->record('payment', $payment->id, 'settlement_failed', null, ['error' => class_basename($e)]);
            report($e);

            return ['status' => 'settlement_failed', 'code' => 500];
        }

        return ['status' => $outcome, 'code' => 200];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return WebhookResult
     */
    private function flagForReview(Payment $payment, string $reason, array $details): array
    {
        $payment->forceFill(['status' => 'needs_review'])->save();
        $this->audit->record('payment', $payment->id, $reason, null, $details);

        return ['status' => 'needs_review', 'code' => 200];
    }

    /**
     * @return WebhookResult
     */
    private function cancel(Payment $payment): array
    {
        if ($payment->isPaid()) {
            return ['status' => 'ignored', 'code' => 200];
        }

        try {
            app(PaymentService::class)->cancelUnpaid(Order::query()->findOrFail($payment->order_id));
        } catch (CheckoutException) {
            return ['status' => 'ignored', 'code' => 200];
        }

        return ['status' => 'cancelled', 'code' => 200];
    }
}
