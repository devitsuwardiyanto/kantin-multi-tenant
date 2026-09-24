<?php

use App\Models\Menu;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Tenant;
use App\Models\UserTenantRole;
use App\Modules\Admin\Services\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Manajer modifier tenant (konfigurasi untuk UC-04). Pola keamanan sama dengan menu-manager:
 * booted() memverifikasi ulang membership dan mengisi TenantContext tiap request, sehingga
 * grup, opsi, dan menu yang dipasang selalu milik tenant aktif (FK komposit sebagai lapis kedua).
 */
new class extends Component
{
    public int $tenantId = 0;

    public string $groupName = '';

    public int $minSelect = 0;

    public int $maxSelect = 1;

    /** @var array<int|string, string> group_id => nama opsi baru */
    public array $optionName = [];

    /** @var array<int|string, int|string> group_id => selisih harga opsi baru */
    public array $optionPrice = [];

    public function mount(int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function booted(): void
    {
        $user = Auth::user();
        $isMember = $user !== null && UserTenantRole::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $this->tenantId)
            ->exists();
        abort_unless($isMember, 403);

        app(TenantContext::class)->set(Tenant::query()->findOrFail($this->tenantId));
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, ModifierGroup> */
    #[Computed]
    public function groups(): \Illuminate\Database\Eloquent\Collection
    {
        return ModifierGroup::query()->with(['options' => fn ($query) => $query->orderBy('price_delta')->orderBy('id')])
            ->orderBy('name')->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Menu> */
    #[Computed]
    public function menus(): \Illuminate\Database\Eloquent\Collection
    {
        return Menu::query()->with('modifierGroups:id')->orderBy('name')->get();
    }

    public function createGroup(): void
    {
        $data = $this->validate([
            'groupName' => ['required', 'string', 'max:120'],
            'minSelect' => ['required', 'integer', 'min:0', 'max:10'],
            'maxSelect' => ['required', 'integer', 'min:1', 'max:10', 'gte:minSelect'],
        ], [], ['groupName' => 'nama grup', 'minSelect' => 'minimal pilihan', 'maxSelect' => 'maksimal pilihan']);

        ModifierGroup::create([
            'name' => $data['groupName'],
            'min_select' => $data['minSelect'],
            'max_select' => $data['maxSelect'],
            'is_active' => true,
        ]); // tenant_id auto-fill dari context

        $this->reset(['groupName', 'minSelect', 'maxSelect']);
        unset($this->groups);
    }

    public function addOption(int $groupId): void
    {
        $group = ModifierGroup::query()->findOrFail($groupId);
        $this->validate([
            "optionName.{$groupId}" => ['required', 'string', 'max:120'],
            "optionPrice.{$groupId}" => ['required', 'integer', 'min:0', 'max:1000000'],
        ], [], ["optionName.{$groupId}" => 'nama opsi', "optionPrice.{$groupId}" => 'selisih harga']);

        $option = new ModifierOption;
        $option->forceFill([
            'group_id' => $group->id,
            'name' => $this->optionName[$groupId],
            'price_delta' => (int) $this->optionPrice[$groupId],
            'stock_qty' => 0,
            'is_available' => true,
        ])->save(); // tenant_id auto-fill; FK komposit menolak grup lintas tenant

        unset($this->optionName[$groupId], $this->optionPrice[$groupId], $this->groups);
    }

    /** Opsi habis tetap tampil nonaktif di formulir pelanggan (UC-04 alur 2a). */
    public function toggleOption(int $optionId): void
    {
        $option = ModifierOption::query()->findOrFail($optionId);
        $option->forceFill(['is_available' => ! $option->is_available])->save();

        app(AuditLogger::class)->record('modifier_option', $option->id, 'availability_changed', null,
            ['is_available' => $option->is_available], $this->tenantId);
        unset($this->groups);
    }

    public function toggleGroup(int $groupId): void
    {
        $group = ModifierGroup::query()->findOrFail($groupId);
        $group->forceFill(['is_active' => ! $group->is_active])->save();
        unset($this->groups);
    }

    /** Memasang/melepas grup pada menu milik tenant yang sama. */
    public function toggleAttachment(int $groupId, int $menuId): void
    {
        $group = ModifierGroup::query()->findOrFail($groupId);
        $menu = Menu::query()->findOrFail($menuId);

        if ($menu->modifierGroups()->whereKey($group->id)->exists()) {
            $menu->modifierGroups()->detach($group->id);
        } else {
            $menu->modifierGroups()->attach($group->id, ['tenant_id' => $this->tenantId, 'sort_order' => 1]);
        }

        unset($this->menus);
    }
};
?>

<div class="space-y-6">
    <form wire:submit="createGroup" class="grid grid-cols-1 gap-3 rounded-xl border border-zinc-200 p-4 sm:grid-cols-4 dark:border-zinc-800">
        <label class="sm:col-span-2 text-sm">Nama grup
            <input type="text" wire:model="groupName" placeholder="Mis. Ukuran, Topping" class="mt-1 min-h-11 w-full rounded-lg border border-zinc-300 px-3 dark:border-zinc-600 dark:bg-zinc-800">
            @error('groupName') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm">Minimal pilihan
            <input type="number" min="0" wire:model="minSelect" class="mt-1 min-h-11 w-full rounded-lg border border-zinc-300 px-3 dark:border-zinc-600 dark:bg-zinc-800">
            @error('minSelect') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm">Maksimal pilihan
            <input type="number" min="1" wire:model="maxSelect" class="mt-1 min-h-11 w-full rounded-lg border border-zinc-300 px-3 dark:border-zinc-600 dark:bg-zinc-800">
            @error('maxSelect') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <button type="submit" class="min-h-11 rounded-lg bg-zinc-900 px-4 font-medium text-white sm:col-span-4 dark:bg-white dark:text-zinc-900">Tambah Grup</button>
    </form>

    @forelse ($this->groups as $group)
        <section wire:key="group-{{ $group->id }}" class="rounded-xl border border-zinc-200 dark:border-zinc-800" data-test="modifier-group">
            <div class="flex items-center justify-between border-b border-zinc-200 p-3 dark:border-zinc-800">
                <div>
                    <h2 class="font-extrabold uppercase tracking-wide">{{ $group->name }}</h2>
                    <p class="text-xs text-zinc-500">{{ $group->min_select > 0 ? 'Wajib' : 'Opsional' }} · pilih {{ $group->min_select }}–{{ $group->max_select }}</p>
                </div>
                <button type="button" wire:click="toggleGroup({{ $group->id }})" role="switch" aria-checked="{{ $group->is_active ? 'true' : 'false' }}"
                    @class(['min-h-9 rounded-full px-3 text-xs font-bold', 'bg-green-600 text-white' => $group->is_active, 'bg-zinc-300 text-zinc-700' => ! $group->is_active])>
                    {{ $group->is_active ? 'AKTIF' : 'NONAKTIF' }}
                </button>
            </div>

            <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($group->options as $option)
                    <li wire:key="option-{{ $option->id }}" class="flex items-center justify-between px-3 py-2 text-sm">
                        <span @class(['font-medium', 'text-zinc-400 line-through' => ! $option->is_available])>{{ $option->name }} · +Rp{{ number_format($option->price_delta, 0, ',', '.') }}</span>
                        <button type="button" wire:click="toggleOption({{ $option->id }})" role="switch" aria-checked="{{ $option->is_available ? 'true' : 'false' }}"
                            @class(['min-h-8 rounded-full px-3 text-xs font-bold', 'bg-green-600 text-white' => $option->is_available, 'bg-zinc-300 text-zinc-700' => ! $option->is_available])>
                            {{ $option->is_available ? 'TERSEDIA' : 'HABIS' }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <form wire:submit="addOption({{ $group->id }})" class="flex flex-wrap gap-2 border-t border-zinc-200 p-3 dark:border-zinc-800">
                <input type="text" wire:model="optionName.{{ $group->id }}" placeholder="Nama opsi" aria-label="Nama opsi" class="min-h-10 flex-1 rounded-lg border border-zinc-300 px-3 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                <input type="number" min="0" wire:model="optionPrice.{{ $group->id }}" placeholder="+Rp" aria-label="Selisih harga" class="min-h-10 w-28 rounded-lg border border-zinc-300 px-3 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                <button type="submit" class="min-h-10 rounded-lg border border-zinc-300 px-3 text-sm font-medium dark:border-zinc-600">+ Opsi</button>
                @error("optionName.{$group->id}") <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
                @error("optionPrice.{$group->id}") <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
            </form>

            <div class="border-t border-zinc-200 p-3 dark:border-zinc-800">
                <p class="mb-2 text-xs font-semibold uppercase text-zinc-500">Dipasang pada menu</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->menus as $menu)
                        @php($attached = $menu->modifierGroups->contains('id', $group->id))
                        <button type="button" wire:key="attach-{{ $group->id }}-{{ $menu->id }}" wire:click="toggleAttachment({{ $group->id }}, {{ $menu->id }})" aria-pressed="{{ $attached ? 'true' : 'false' }}"
                            @class(['min-h-9 rounded-full border px-3 text-sm', 'border-red-600 bg-red-600 text-white' => $attached, 'border-zinc-300' => ! $attached])>
                            {{ $menu->name }}
                        </button>
                    @endforeach
                </div>
            </div>
        </section>
    @empty
        <x-empty-state title="Belum ada grup modifier" description="Buat grup seperti Ukuran atau Topping, lalu pasang pada menu." />
    @endforelse
</div>
