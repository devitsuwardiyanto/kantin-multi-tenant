<?php

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Tenant;
use App\Models\UserTenantRole;
use App\Modules\Catalog\Services\MenuStockService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Manajer menu tenant. tenantId adalah prop publik (tidak dipercaya): booted() memverifikasi
 * ulang membership aktor DAN mengisi TenantContext pada SETIAP request (Livewire update tidak
 * melewati middleware route). Semua query lalu ter-scope otomatis ke tenant aktif.
 */
new class extends Component
{
    public int $tenantId = 0;

    public string $name = '';

    public ?int $categoryId = null;

    public int $basePrice = 0;

    public int $prepMinutes = 10;

    public int $stockQty = 0;

    public string $newCategory = '';

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

    #[Computed]
    public function menus(): \Illuminate\Database\Eloquent\Collection
    {
        return Menu::query()->with('category:id,name')->orderBy('name')->get();
    }

    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return MenuCategory::query()->orderBy('name')->get();
    }

    public function addCategory(): void
    {
        $data = $this->validate(['newCategory' => ['required', 'string', 'max:120']]);
        MenuCategory::create(['name' => $data['newCategory']]); // tenant_id auto-fill dari context
        $this->newCategory = '';
        unset($this->categories);
    }

    public function createMenu(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'categoryId' => ['required', 'integer'],
            'basePrice' => ['required', 'integer', 'min:0'],
            'prepMinutes' => ['required', 'integer', 'min:0', 'max:240'],
            'stockQty' => ['required', 'integer', 'min:0'],
        ]);

        // Kategori harus milik tenant aktif — global scope menjamin (findOrFail 404 bila lintas tenant).
        $category = MenuCategory::query()->findOrFail($data['categoryId']);

        $menu = new Menu;
        $menu->forceFill([
            'category_id' => $category->id,
            'name' => $data['name'],
            'base_price' => $data['basePrice'],
            'prep_minutes' => $data['prepMinutes'],
            'stock_qty' => $data['stockQty'],
            'is_available' => true,
        ]);
        $menu->save(); // tenant_id auto-fill dari context

        $this->reset(['name', 'basePrice', 'prepMinutes', 'stockQty', 'categoryId']);
        unset($this->menus);
        session()->flash('status', 'Menu dibuat.');
    }

    public function toggle(int $menuId): void
    {
        $menu = Menu::query()->findOrFail($menuId); // ter-scope tenant aktif
        app(MenuStockService::class)->toggleAvailability($menu);
        unset($this->menus);
    }

    public function restock(int $menuId, int $delta): void
    {
        $menu = Menu::query()->findOrFail($menuId);
        app(MenuStockService::class)->adjust($menu, $delta, 'restock', (string) Str::uuid(), 'manual restock');
        unset($this->menus);
    }
};
?>

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg bg-green-100 px-4 py-2 text-sm text-green-800 dark:bg-green-900/40 dark:text-green-300">{{ session('status') }}</div>
    @endif

    <section>
        <h2 class="font-semibold">Kategori</h2>
        <div class="mt-2 flex flex-wrap items-end gap-2">
            <x-input name="newCategory" label="Kategori baru" wire:model="newCategory" />
            <x-button type="button" wire:click="addCategory">Tambah Kategori</x-button>
        </div>
        <p class="mt-2 text-sm text-zinc-500">{{ $this->categories->pluck('name')->join(', ') ?: 'Belum ada kategori.' }}</p>
    </section>

    <section>
        <h2 class="font-semibold">Buat Menu</h2>
        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <x-input name="name" label="Nama" wire:model="name" />
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Kategori</label>
                <select wire:model="categoryId" class="min-h-11 rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                    <option value="">Pilih kategori</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}" wire:key="cat-{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-input name="basePrice" label="Harga (Rp)" type="number" wire:model="basePrice" />
            <x-input name="stockQty" label="Stok" type="number" wire:model="stockQty" />
        </div>
        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('categoryId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        <x-button type="button" wire:click="createMenu" class="mt-3">Simpan Menu</x-button>
    </section>

    <section>
        <h2 class="font-semibold">Menu</h2>
        <div class="mt-2 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    <tr><th class="px-4 py-2">Nama</th><th class="px-4 py-2">Kategori</th><th class="px-4 py-2">Harga</th><th class="px-4 py-2">Stok</th><th class="px-4 py-2">Status</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody>
                    @forelse ($this->menus as $menu)
                        <tr wire:key="menu-{{ $menu->id }}" class="border-t border-zinc-200 dark:border-zinc-800">
                            <td class="px-4 py-3">{{ $menu->name }}</td>
                            <td class="px-4 py-3">{{ $menu->category?->name }}</td>
                            <td class="px-4 py-3">Rp {{ number_format($menu->base_price, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 {{ $menu->stock_qty <= 5 ? 'font-semibold text-amber-600' : '' }}">{{ $menu->stock_qty }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$menu->is_available ? 'active' : 'suspended'">{{ $menu->is_available ? 'tersedia' : 'habis' }}</x-status-badge></td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" wire:click="toggle({{ $menu->id }})" class="me-2 text-sm underline">{{ $menu->is_available ? 'Tandai habis' : 'Aktifkan' }}</button>
                                <button type="button" wire:click="restock({{ $menu->id }}, 10)" class="text-sm underline">+10 stok</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-3" colspan="6"><x-empty-state title="Belum ada menu" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
