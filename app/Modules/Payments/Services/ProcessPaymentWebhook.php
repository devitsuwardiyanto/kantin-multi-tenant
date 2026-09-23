<?php

namespace App\Modules\Payments\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Modules\Payments\Support\WebhookSignatureVerifier;
use Illuminate\Support\Facades\DB;

/**
 * Memproses webhook pembayaran provider. Urutan pertahanan:
 *
 *  1. Verifikasi signature atas RAW BODY (HMAC) — invalid → 401, tanpa efek samping.
 *  2. Dedup via provider_event_id UNIQUE (append-only payment_events) — replay → 200 duplicate.
 *  3. Pada status sukses: catat event + tandai lunas + settlement, SEMUANYA dalam satu transaksi
 *     (all-or-nothing; kegagalan → rollback → provider mengirim ulang).
 *
 * @phpstan-type WebhookResult array{status: string, code: int}
 */
final class ProcessPaymentWebhook
{
    public function __construct(
        private WebhookSignatureVerifier $verifier,
        private SettlePayment $settle,
    ) {}

    /**
     * @return WebhookResult
     */
    public function handle(string $rawBody, ?string $signature): array
    {
        if (! $this->verifier->verify($rawBody, $signature)) {
            return ['status' => 'invalid_signature', 'code' => 401];
        }

        $data = json_decode($rawBody, true);
        if (! is_array($data)) {
            return ['status' => 'bad_request', 'code' => 400];
        }

        $eventId = $data['event_id'] ?? null;
        $reference = $data['payment_reference'] ?? null;
        $status = $data['status'] ?? null;
        if (! is_string($eventId) || ! is_string($reference) || ! is_string($status)) {
            return ['status' => 'bad_request', 'code' => 400];
        }

        $payment = Payment::query()->where('payment_reference', $reference)->first();
        if ($payment === null) {
            return ['status' => 'not_found', 'code' => 404];
        }

        if (PaymentEvent::query()->where('provider_event_id', $eventId)->exists()) {
            return ['status' => 'duplicate', 'code' => 200];
        }

        $success = $status === 'success';

        DB::transaction(function () use ($payment, $eventId, $signature, $data, $success): void {
            $attempt = $payment->latestAttempt()->first();

            $event = new PaymentEvent;
            $event->forceFill([
                'payment_id' => $payment->id,
                'payment_attempt_id' => $attempt?->id,
                'provider_event_id' => $eventId,
                'signature' => $signature,
                'payload' => $data,
                'result' => $success ? 'verified' : 'rejected',
                'received_at' => now(),
            ])->save();

            if (! $success) {
                return;
            }

            if ($attempt !== null && $attempt->status !== 'success') {
                $attempt->forceFill(['status' => 'success'])->save();
            }

            if (! $payment->isPaid()) {
                $payment->forceFill(['status' => 'paid', 'settled_at' => now()])->save();
                Order::query()->whereKey($payment->order_id)->update(['status' => 'paid']);
            }

            $this->settle->settle($payment);
        });

        return ['status' => $success ? 'ok' : 'rejected_recorded', 'code' => 200];
    }
}
