<?php

namespace App\Modules\Admin\Services;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\Tenant;
use App\Models\TenantBalance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * UC-21 onboarding: membuat tenant, komisi aktif awal, balance nol, akun penanggung jawab
 * (owner) dan rekening bank secara atomik. Kredensial awal dikirim SETELAH commit berupa tautan
 * atur-kata-sandi (password broker), bukan kata sandi mentah: kata sandi acak awal tidak pernah
 * diketahui siapa pun.
 */
final class CreateTenant
{
    public function __construct(
        private AuditLogger $audit,
        private ManageBankAccount $bankAccounts,
        private AssignTenantRole $roles,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Canteen $canteen, array $data): Tenant
    {
        return DB::transaction(function () use ($canteen, $data): Tenant {
            $tenant = (new Tenant)->forceFill([
                'canteen_id' => $canteen->id,
                'code' => $data['code'],
                'slug' => $data['slug'],
                'display_name' => $data['display_name'],
                'status' => 'active',
            ]);
            $tenant->save();

            (new TenantBalance)->forceFill([
                'tenant_id' => $tenant->id,
                'available_amount' => 0,
                'held_amount' => 0,
            ])->save();

            $commission = (new CommissionScheme)->forceFill([
                'tenant_id' => $tenant->id,
                'commission_rate' => $data['commission_rate'],
                'valid_from' => now(),
                'valid_to' => null,
            ]);
            $commission->save();

            $this->audit->record('tenant', $tenant->id, 'created',
                null, $tenant->only(['code', 'slug', 'display_name', 'status']),
                $tenant->id, $canteen->id);
            $this->audit->record('commission_scheme', $commission->id, 'created',
                null, $commission->only(['commission_rate', 'valid_from']), $tenant->id, $canteen->id);

            if (isset($data['pic_email'])) {
                $owner = (new User)->forceFill([
                    'name' => $data['pic_name'],
                    'email' => $data['pic_email'],
                    'password' => Str::password(32),
                    'role' => 'tenant',
                    'status' => 'active',
                    'email_verified_at' => now(), // diverifikasi pengelola saat onboarding
                ]);
                $owner->save();
                $this->roles->assign($tenant, $owner, 'owner');

                DB::afterCommit(fn () => Password::broker()->sendResetLink(['email' => $owner->email]));
            }

            if (isset($data['account_number'])) {
                $this->bankAccounts->store($tenant, $data)->forceFill(['is_primary' => true])->save();
            }

            return $tenant;
        });
    }
}
