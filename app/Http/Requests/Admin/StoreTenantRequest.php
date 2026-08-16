<?php

namespace App\Http\Requests\Admin;

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
        ];
    }
}
