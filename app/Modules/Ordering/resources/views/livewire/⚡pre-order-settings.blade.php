<?php

use App\Models\Tenant;
use App\Models\UserTenantRole;
use App\Modules\Admin\Services\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Pengaturan pre-order tenant (UC-06 prasyarat 2). Pola keamanan sama dengan menu-manager:
 * booted() memverifikasi ulang membership tiap request; perubahan dicatat di audit log.
 */
new class extends Component
{
    public int $tenantId = 0;

    public bool $enabled = false;

    public int $slotCapacity = 5;

    public bool $saved = false;

    public function mount(int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $tenant = $this->tenant();
        $this->enabled = $tenant->pre_order_enabled;
        $this->slotCapacity = $tenant->pre_order_slot_capacity;
    }

    public function booted(): void
    {
        $user = Auth::user();
        $isMember = $user !== null && UserTenantRole::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $this->tenantId)
            ->exists();
        abort_unless($isMember, 403);

        app(TenantContext::class)->set($this->tenant());
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail($this->tenantId);
    }

    public function save(): void
    {
        $this->validate([
            'enabled' => ['boolean'],
            'slotCapacity' => ['required', 'integer', 'min:1', 'max:50'],
        ], [], ['slotCapacity' => 'kapasitas per slot']);

        $tenant = $this->tenant();
        $before = ['pre_order_enabled' => $tenant->pre_order_enabled, 'pre_order_slot_capacity' => $tenant->pre_order_slot_capacity];
        $tenant->forceFill(['pre_order_enabled' => $this->enabled, 'pre_order_slot_capacity' => $this->slotCapacity])->save();

        app(AuditLogger::class)->record('tenant', $tenant->id, 'pre_order_settings_updated', $before,
            ['pre_order_enabled' => $this->enabled, 'pre_order_slot_capacity' => $this->slotCapacity], $tenant->id, $tenant->canteen_id);

        $this->saved = true;
    }
};
?>

<form wire:submit="save" class="max-w-md space-y-4 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
    <label class="flex items-center justify-between gap-3">
        <span>
            <span class="block font-semibold">Terima pre-order (Pesan dulu / Pick-up)</span>
            <span class="block text-xs text-zinc-500">Pelanggan dapat menjadwalkan waktu ambil ≥ 15 menit ke depan, dalam jam operasional.</span>
        </span>
        <input type="checkbox" wire:model="enabled" class="size-5" data-test="pre-order-enabled" />
    </label>

    <div>
        <label for="slotCapacity" class="block text-sm font-semibold">Kapasitas per slot 15 menit</label>
        <input id="slotCapacity" type="number" min="1" max="50" wire:model="slotCapacity"
            class="mt-1 w-28 rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-900" />
        @error('slotCapacity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-zinc-900">Simpan</button>
        @if ($saved)<span class="text-sm text-green-700" role="status">Tersimpan.</span>@endif
    </div>
</form>
