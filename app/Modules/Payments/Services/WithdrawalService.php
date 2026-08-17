<?php

namespace App\Modules\Payments\Services;

use App\Models\TenantBalance;
use App\Models\TenantBankAccount;
use App\Models\User;
use App\Models\Withdrawal;
use App\Modules\Admin\Services\AuditLogger;
use App\Modules\Payments\Exceptions\WithdrawalException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Penarikan dana tenant berbasis ledger APPEND-ONLY.
 *
 *  - request(): tahan dana (available → held) via ledger 'hold' + buat withdrawal. SATU penarikan
 *    aktif per tenant ditegakkan kolom UNIQUE active_tenant_lock (diisi saat aktif, null saat final).
 *  - approve(): cairkan (held keluar) via ledger 'withdrawal_debit'.
 *  - reject(): lepas tahanan (held → available) via ledger 'release'.
 *
 * Semua transisi atomik; saldo dimutasi bersamaan entri ledger; CHECK non-negatif menjaga saldo.
 */
final class WithdrawalService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @throws WithdrawalException
     */
    public function request(TenantBankAccount $account, int $amount, User $requester): Withdrawal
    {
        if ($amount <= 0) {
            throw WithdrawalException::invalidAmount();
        }

        $tenantId = (int) $account->tenant_id;
        if ($account->status !== 'verified') {
            throw WithdrawalException::accountNotUsable();
        }

        $available = (int) (TenantBalance::query()->firstOrNew(['tenant_id' => $tenantId])->available_amount ?? 0);
        if ($amount > $available) {
            throw WithdrawalException::insufficientFunds();
        }

        try {
            return DB::transaction(function () use ($account, $tenantId, $amount, $requester): Withdrawal {
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

                $this->audit->record('withdrawal', $withdrawal->id, 'requested', null, ['amount' => $amount], $tenantId);

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
    public function approve(Withdrawal $withdrawal, User $reviewer): Withdrawal
    {
        if ($withdrawal->status !== 'requested') {
            throw WithdrawalException::notReviewable();
        }

        return DB::transaction(function () use ($withdrawal, $reviewer): Withdrawal {
            $tenantId = (int) $withdrawal->tenant_id;
            $amount = (int) $withdrawal->amount;

            $this->ledger($tenantId, $withdrawal->id, 'withdrawal_debit', 0, -$amount, 'debit');
            $this->adjustBalance($tenantId, 0, -$amount);

            $withdrawal->forceFill([
                'status' => 'paid',
                'reviewed_by' => $reviewer->id,
                'active_tenant_lock' => null,
                'transfer_snapshot' => ['reviewed_by' => $reviewer->id, 'at' => now()->toIso8601String()],
            ])->save();

            $this->audit->record('withdrawal', $withdrawal->id, 'paid', null, ['amount' => $amount], $tenantId, null);

            return $withdrawal;
        });
    }

    /**
     * Menolak: lepas tahanan (held → available). Lock dilepas (null).
     *
     * @throws WithdrawalException
     */
    public function reject(Withdrawal $withdrawal, User $reviewer): Withdrawal
    {
        if ($withdrawal->status !== 'requested') {
            throw WithdrawalException::notReviewable();
        }

        return DB::transaction(function () use ($withdrawal, $reviewer): Withdrawal {
            $tenantId = (int) $withdrawal->tenant_id;
            $amount = (int) $withdrawal->amount;

            $this->ledger($tenantId, $withdrawal->id, 'release', $amount, -$amount, 'release');
            $this->adjustBalance($tenantId, $amount, -$amount);

            $withdrawal->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $reviewer->id,
                'active_tenant_lock' => null,
            ])->save();

            $this->audit->record('withdrawal', $withdrawal->id, 'rejected', null, ['amount' => $amount], $tenantId, null);

            return $withdrawal;
        });
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

    private function adjustBalance(int $tenantId, int $availableDelta, int $heldDelta): void
    {
        DB::table('tenant_balances')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'available_amount' => 0,
            'held_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
