<?php

use App\Models\Canteen;
use App\Modules\Catalog\Services\PublicCatalogQuery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $canteenSlug = '';

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $tenantId = null;

    public function mount(string $canteenSlug): void
    {
        $this->canteenSlug = $canteenSlug;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTenantId(): void
    {
        $this->resetPage();
    }

    /**
     * Meneruskan permintaan tambah ke komponen keranjang (yang memegang sesi tepercaya).
     * Katalog sendiri anonim; ia tidak menyimpan/menentukan harga.
     */
    public function add(int $menuId): void
    {
        $this->dispatch('cart-add', menuId: $menuId);
    }

    #[Computed]
    public function canteen(): ?Canteen
    {
        return Canteen::query()->where('slug', $this->canteenSlug)->where('status', 'active')->first();
    }

    #[Computed]
    public function menus(): ?\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $canteen = $this->canteen();

        return $canteen ? app(PublicCatalogQuery::class)->browse($canteen, $this->search, $this->tenantId) : null;
    }

    #[Computed]
    public function tenants(): \Illuminate\Support\Collection
    {
        $canteen = $this->canteen();

        return $canteen ? app(PublicCatalogQuery::class)->activeTenants($canteen) : collect();
    }
};
?>

<div class="space-y-4">
    @if (! $this->canteen)
        <x-empty-state title="Kantin tidak ditemukan" description="Pindai QR meja untuk membuka katalog." />
    @else
        <div class="flex flex-col gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari menu…"
                class="min-h-11 rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-600 dark:bg-zinc-800" />
            <select wire:model.live="tenantId"
                class="min-h-11 rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                <option value="">Semua tenant</option>
                @foreach ($this->tenants as $tenant)
                    <option value="{{ $tenant->id }}" wire:key="tenant-{{ $tenant->id }}">{{ $tenant->display_name }}</option>
                @endforeach
            </select>
        </div>

        <div wire:loading class="text-sm text-zinc-500">Memuat…</div>

        @forelse ($this->menus as $menu)
            <div wire:key="menu-{{ $menu->id }}" class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $menu->name }}</p>
                        <p class="truncate text-xs text-zinc-500">{{ $menu->tenant->display_name }} · {{ $menu->category?->name }}</p>
                    </div>
                    <p class="shrink-0 font-semibold">Rp {{ number_format($menu->base_price, 0, ',', '.') }}</p>
                </div>
                <button type="button" wire:click="add({{ $menu->id }})"
                    class="mt-2 min-h-9 rounded-lg border border-zinc-300 px-3 text-sm font-medium dark:border-zinc-600">
                    + Tambah
                </button>
            </div>
        @empty
            <x-empty-state title="Belum ada menu" description="Tidak ada menu tersedia untuk filter ini." />
        @endforelse

        @if ($this->menus)
            <div>{{ $this->menus->links() }}</div>
        @endif
    @endif
</div>
