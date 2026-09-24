<?php

namespace App\Modules\Admin\Http\Requests;

use App\Models\Tenant;
use App\Models\UserCanteenRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tenant::class) ?? false;
    }

    /** Canteen berasal dari keanggotaan aktor (tepercaya), bukan input. */
    public function canteenId(): ?int
    {
        $value = UserCanteenRole::query()
            ->where('user_id', $this->user()?->id)
            ->whereIn('role', ['owner', 'manager'])
            ->value('canteen_id');

        return $value !== null ? (int) $value : null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $canteenId = $this->canteenId();

        return [
            'code' => ['required', 'alpha_dash', 'max:30',
                Rule::unique('tenants', 'code')->where(fn ($q) => $q->where('canteen_id', $canteenId))],
            'slug' => ['required', 'alpha_dash', 'max:100',
                Rule::unique('tenants', 'slug')->where(fn ($q) => $q->where('canteen_id', $canteenId))],
            'display_name' => ['required', 'string', 'max:120'],
            'commission_rate' => ['required', 'numeric', 'between:0,100'],
            // UC-21 langkah 3: penanggung jawab (akun owner awal) + rekening bank tenant.
            'pic_name' => ['required', 'string', 'max:120'],
            'pic_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'bank_code' => ['required', 'string', 'max:20'],
            'account_holder' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'digits_between:6,20'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'display_name' => 'nama tampilan',
            'code' => 'kode',
            'commission_rate' => 'komisi',
            'pic_name' => 'nama penanggung jawab',
            'pic_email' => 'surel penanggung jawab',
            'bank_code' => 'bank',
            'account_holder' => 'nama pemilik rekening',
            'account_number' => 'nomor rekening',
        ];
    }
}
