<?php

namespace App\Modules\Payments\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\TenantOrder;
use App\Models\User;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Ordering\Services\CancelUnpaidOrder;
use App\Modules\Payments\Exceptions\FollowUpException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Tindak lanjut pengelola kantin atas kasus yang ditandai sistem (temuan audit Pertemuan 14):
 *
 *  - UC-08 alur 3a — pembayaran `needs_review` (nominal tidak cocok / dibayar setelah pesanan
 *    batal): pengelola MENERIMA (pesanan dilunasi + settlement UC-10) atau menyatakan dana
 *    DIKEMBALIKAN (pesanan dibatalkan, stok kembali).
 *  - UC-15 alur 4a — sub-pesanan dibatalkan dapur (`refund_status = pending`): pengelola
 *    mengembalikan dana; pendapatan tenant & kredit pengelola dibalik dengan entri reversal.
 *
 * Setiap keputusan: baris dikunci, idempoten, catatan wajib, audit append-only, dan hanya untuk
 * kantin milik pengelola (canteenId dari keanggotaan, bukan input klien).
 */
final class FollowUpService
{
    public function __construct(
        private SettlePayment $settle,
        private CancelUnpaidOrder $cancelUnpaid,
        private AuditLogger $audit,
    ) {}

    public function acceptPayment(Payment $payment, int $canteenId, User $reviewer, string $note): Payment
    {
        $note = $this->requireNote($note);

        return DB::transaction(function () use ($payment, $canteenId, $reviewer, $note): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
            $this->assertCanteen($order, $canteenId);

            if ($locked->review_outcome === 'accepted') {
                return $locked; // idempoten: klik ganda
            }
            if ($locked->status !== 'needs_review') {
                throw FollowUpException::notReviewable();
            }
            if ($order->status !== 'awaiting_payment') {
                throw FollowUpException::cannotAccept();
            }

            $locked->forceFill([
                'status' => 'paid', 'settled_at' => now(),
                'review_outcome' => 'accepted', 'review_note' => $note, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(),
            ])->save();
            $order->forceFill(['status' => 'paid'])->save();
            $this->settle->settle($locked);

            $this->audit->record('payment', $locked->id, 'review_accepted', ['status' => 'needs_review'], ['status' => 'paid', 'note' => $note], null, $order->canteen_id);

            return $locked;
        });
    }

    public function refundPayment(Payment $payment, int $canteenId, User $reviewer, string $note): Payment
    {
        $note = $this->requireNote($note);

        return DB::transaction(function () use ($payment, $canteenId, $reviewer, $note): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
            $this->assertCanteen($order, $canteenId);

            if ($locked->review_outcome === 'refunded') {
                return $locked;
            }
            if ($locked->status !== 'needs_review') {
                throw FollowUpException::notReviewable();
            }

            $locked->forceFill([
                'status' => 'refunded',
                'review_outcome' => 'refunded', 'review_note' => $note, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(),
            ])->save();

            // Pesanan yang masih menunggu pembayaran dibatalkan agar stok kembali.
            if ($order->status === 'awaiting_payment') {
                $this->cancelUnpaid->cancel($order);
            }

            $this->audit->record('payment', $locked->id, 'review_refunded', ['status' => 'needs_review'], ['status' => 'refunded', 'note' => $note], null, $order->canteen_id);

            return $locked;
        });
    }

    public function refundTenantOrder(TenantOrder $tenantOrder, int $canteenId, User $reviewer, string $note): TenantOrder
    {
        $note = $this->requireNote($note);

        try {
            return DB::transaction(function () use ($tenantOrder, $canteenId, $reviewer, $note): TenantOrder {
                $locked = TenantOrder::query()->withoutGlobalScope('tenant')->lockForUpdate()->findOrFail($tenantOrder->id);
                $order = Order::query()->findOrFail($locked->order_id);
                $this->assertCanteen($order, $canteenId);

                if ($locked->refund_status === 'refunded') {
                    return $locked;
                }
                if ($locked->status !== 'cancelled' || $locked->refund_status !== 'pending') {
                    throw FollowUpException::notRefundable();
                }

                $payment = Payment::query()->where('order_id', $order->id)->where('status', 'paid')->first();
                if ($payment !== null) {
                    $this->settle->reverseTenantOrder($payment, $locked);
                }

                $amount = (int) $locked->subtotal_amount + (int) $locked->tax_amount + (int) $locked->service_fee_amount;
                $locked->forceFill([
                    'refund_status' => 'refunded', 'refund_amount' => $amount, 'refund_note' => $note,
                    'refunded_by' => $reviewer->id, 'refunded_at' => now(),
                ])->save();

                $this->audit->record('tenant_order', $locked->id, 'refunded', ['refund_status' => 'pending'],
                    ['refund_status' => 'refunded', 'amount' => $amount, 'note' => $note], (int) $locked->tenant_id, $order->canteen_id);

                return $locked;
            });
        } catch (QueryException $e) {
            // CHECK saldo tenant non-negatif menolak pembalikan (fail-closed): dana sudah ditarik.
            if (str_contains($e->getMessage(), 'tenant_balances')) {
                throw FollowUpException::insufficientTenantBalance();
            }

            throw $e;
        }
    }

    private function assertCanteen(Order $order, int $canteenId): void
    {
        if ((int) $order->canteen_id !== $canteenId) {
            throw FollowUpException::notFound();
        }
    }

    private function requireNote(string $note): string
    {
        $note = trim(strip_tags($note));
        if ($note === '') {
            throw FollowUpException::noteRequired();
        }

        return mb_substr($note, 0, 500);
    }
}
