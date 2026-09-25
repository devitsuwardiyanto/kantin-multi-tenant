<?php

namespace App\Modules\Payments\Services;

use App\Models\TenantBankAccount;
use App\Models\User;
use App\Models\UserCanteenRole;
use App\Models\Withdrawal;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Payments\Exceptions\WithdrawalException;
use App\Modules\Payments\Notifications\WithdrawalRequested;
use App\Modules\Payments\Notifications\WithdrawalReviewed;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Penarikan dana tenant berbasis ledger APPEND-ONLY.
 *
 *  - request() (UC-20): dalam satu transaksi baris saldo DIKUNCI, lalu nominal divalidasi
 *    terhadap saldo tersedia + minimum; tahan dana (available → held) via ledger 'hold'. SATU
 *    penarikan aktif per tenant ditegakkan kolom UNIQUE active_tenant_lock.
 *  - approve() (UC-23): baris withdrawal dikunci; idempoten (sudah dicairkan → tanpa efek);
 *    ledger tidak sesuai → status investigation (tak dapat disetujui); wajib bukti transfer;
 *    cairkan (held keluar) via ledger 'withdrawal_debit'.
 *  - reject() (UC-23 alur 4a): wajib alasan; lepas tahanan (held → available) via ledger 'release'.
 *
 * Semua transisi tercatat di audit log; CHECK non-negatif menjaga saldo. Notifikasi dikirim
 * setelah commit (pengelola saat diajukan, tenant saat diputuskan).
 */
final class WithdrawalService
{
    public function __construct(private AuditLogger $audit) {}

    public static function minimum(): int
    {
        return (int) config('services.withdrawal.minimum', 100000);
    }

    /**
     * @throws WithdrawalException
     */
    public function request(TenantBankAccount $account, int $amount, User $requester): Withdrawal
    {
        if ($amount <= 0) {
            throw WithdrawalException::invalidAmount();
        }
        if ($amount < self::minimum()) {
            throw WithdrawalException::belowMinimum(self::minimum());
        }

        $tenantId = (int) $account->tenant_id;
        if ($account->status !== 'verified') {
            throw WithdrawalException::accountNotUsable();
        }

        try {
            return DB::transaction(function () use ($account, $tenantId, $amount, $requester): Withdrawal {
                $this->ensureBalanceRow($tenantId);
                $balance = DB::table('tenant_balances')->where('tenant_id', $tenantId)->lockForUpdate()->first();

                // Alur 2b: pengajuan sebelumnya masih berjalan (diperiksa setelah kunci saldo).
                if (Withdrawal::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->whereIn('status', Withdrawal::ACTIVE_STATUSES)->exists()) {
                    throw WithdrawalException::alreadyActive();
                }

                // Alur 2a: saldo divalidasi SETELAH baris dikunci (tidak ada balapan antarpermintaan).
                $available = (int) ($balance->available_amount ?? 0);
                if ($amount > $available) {
                    throw WithdrawalException::insufficientFunds($available);
                }

                $withdrawal = new Withdrawal;
                $withdrawal->forceFill([
                    'tenant_id' => $tenantId,
                    'bank_account_id' => $account->id,
                    'requested_by' => $requester->id,
                    'idempotency_key' => 'wd-'.Str::uuid(),
                    'amount' => $amount,
                    'status' => 'requested',
                    'active_tenant_lock' => $tenantId, // satu penarikan aktif per tenant
                ])->save();

                $this->ledger($tenantId, $withdrawal->id, 'hold', -$amount, $amount, 'hold');
                $this->adjustBalance($tenantId, -$amount, $amount);

                $this->audit->record('withdrawal', $withdrawal->id, 'requested', null, ['amount' => $amount, 'status' => 'requested'], $tenantId);

                // Langkah 5: beri tahu pengelola kantin untuk verifikasi (UC-23).
                DB::afterCommit(fn () => Notification::send($this->canteenReviewers($tenantId), new WithdrawalRequested($withdrawal)));

                return $withdrawal;
            });
        } catch (UniqueConstraintViolationException) {
            throw WithdrawalException::alreadyActive();
        }
    }

    /**
     * Menyetujui & mencairkan: held keluar (withdrawal_debit). Lock dilepas (null).
     *
     * @throws WithdrawalException
     */
    public function approve(Withdrawal $withdrawal, User $reviewer, ?string $proofPath): Withdrawal
    {
        if ($proofPath === null || $proofPath === '') {
            throw WithdrawalException::proofRequired();
        }

        $mismatch = false;
        $result = DB::transaction(function () use ($withdrawal, $reviewer, $proofPath, &$mismatch): Withdrawal {
            $locked = Withdrawal::query()->withoutGlobalScope('tenant')->lockForUpdate()->findOrFail($withdrawal->id);

            // Idempoten: persetujuan kedua (mis. klik ganda / dua pengelola bersamaan) tanpa efek.
            if ($locked->status === 'paid') {
                return $locked;
            }
            if ($locked->status !== 'requested') {
                throw WithdrawalException::notReviewable();
            }

            $tenantId = (int) $locked->tenant_id;
            $amount = (int) $locked->amount;

            // Alur 3a: saldo materialisasi harus sama dengan akumulasi ledger.
            if (! $this->ledgerMatches($tenantId)) {
                $locked->forceFill(['status' => 'investigation', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()])->save();
                $this->audit->record('withdrawal', $locked->id, 'investigation', ['status' => 'requested'], ['status' => 'investigation'], $tenantId);
                $mismatch = true;

                return $locked;
            }

            $this->ledger($tenantId, $locked->id, 'withdrawal_debit', 0, -$amount, 'debit');
            $this->adjustBalance($tenantId, 0, -$amount);

            $locked->forceFill([
                'status' => 'paid',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'transfer_proof_path' => $proofPath,
                'active_tenant_lock' => null,
                'transfer_snapshot' => ['reviewed_by' => $reviewer->id, 'at' => now()->toIso8601String(), 'proof' => $proofPath],
            ])->save();

            $this->audit->record('withdrawal', $locked->id, 'paid', ['status' => 'requested'], ['status' => 'paid', 'amount' => $amount], $tenantId);

            DB::afterCommit(fn () => $this->notifyTenant($locked));

            return $locked;
        });

        if ($mismatch) {
            throw WithdrawalException::ledgerMismatch();
        }

        return $result;
    }

    /**
     * Menolak: lepas tahanan (held → available). Lock dilepas (null).
     *
     * @throws WithdrawalException
     */
    public function reject(Withdrawal $withdrawal, User $reviewer, ?string $reason): Withdrawal
    {
        $reason = trim((string) $reason);
        if (mb_strlen($reason) < 5) {
            throw WithdrawalException::reasonRequired();
        }

        return DB::transaction(function () use ($withdrawal, $reviewer, $reason): Withdrawal {
            $locked = Withdrawal::query()->withoutGlobalScope('tenant')->lockForUpdate()->findOrFail($withdrawal->id);
            if (! in_array($locked->status, Withdrawal::ACTIVE_STATUSES, true)) {
                throw WithdrawalException::notReviewable();
            }

            $tenantId = (int) $locked->tenant_id;
            $amount = (int) $locked->amount;
            $before = $locked->status;

            $this->ledger($tenantId, $locked->id, 'release', $amount, -$amount, 'release');
            $this->adjustBalance($tenantId, $amount, -$amount);

            $locked->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => mb_substr($reason, 0, 500),
                'active_tenant_lock' => null,
            ])->save();

            $this->audit->record('withdrawal', $locked->id, 'rejected', ['status' => $before], ['status' => 'rejected', 'amount' => $amount, 'reason' => $reason], $tenantId);

            DB::afterCommit(fn () => $this->notifyTenant($locked));

            return $locked;
        });
    }

    /** Saldo materialisasi == akumulasi ledger (tersedia dan tertahan). */
    public function ledgerMatches(int $tenantId): bool
    {
        $ledger = DB::table('ledger_entries')->where('tenant_id', $tenantId)
            ->selectRaw('COALESCE(SUM(available_delta), 0) as available, COALESCE(SUM(held_delta), 0) as held')
            ->first();
        $balance = DB::table('tenant_balances')->where('tenant_id', $tenantId)->first();

        return (int) $ledger->available === (int) ($balance->available_amount ?? 0)
            && (int) $ledger->held === (int) ($balance->held_amount ?? 0);
    }

    /** @return Collection<int, User> */
    private function canteenReviewers(int $tenantId): Collection
    {
        $canteenId = DB::table('tenants')->where('id', $tenantId)->value('canteen_id');
        $userIds = UserCanteenRole::query()->where('canteen_id', $canteenId)->whereIn('role', ['owner', 'manager', 'finance'])->pluck('user_id');

        return User::query()->whereIn('id', $userIds)->get();
    }

    private function notifyTenant(Withdrawal $withdrawal): void
    {
        User::query()->whereKey(DB::table('withdrawals')->where('id', $withdrawal->id)->value('requested_by'))->first()
            ?->notify(new WithdrawalReviewed($withdrawal));
    }

    private function ledger(int $tenantId, int $withdrawalId, string $type, int $availableDelta, int $heldDelta, string $suffix): void
    {
        DB::table('ledger_entries')->insert([
            'tenant_id' => $tenantId,
            'order_id' => null,
            'payment_id' => null,
            'withdrawal_id' => $withdrawalId,
            'idempotency_key' => 'wd:'.$withdrawalId.':'.$suffix,
            'type' => $type,
            'available_delta' => $availableDelta,
            'held_delta' => $heldDelta,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureBalanceRow(int $tenantId): void
    {
        DB::table('tenant_balances')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'available_amount' => 0,
            'held_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function adjustBalance(int $tenantId, int $availableDelta, int $heldDelta): void
    {
        $this->ensureBalanceRow($tenantId);

        // Dua mutasi atomik terpisah (menghindari SQL mentah); serial pada baris yang sama.
        $this->step($tenantId, 'available_amount', $availableDelta);
        $this->step($tenantId, 'held_amount', $heldDelta);
    }

    private function step(int $tenantId, string $column, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $row = DB::table('tenant_balances')->where('tenant_id', $tenantId);
        if ($delta > 0) {
            $row->increment($column, $delta, ['updated_at' => now()]);
        } else {
            $row->decrement($column, -$delta, ['updated_at' => now()]);
        }
    }
}
