<?php

use App\Models\Canteen;
use App\Modules\Catalog\Events\CatalogChanged;
use App\Modules\Catalog\Services\PublicCatalogQuery;
use App\Modules\Catalog\Services\TenantOpeningHours;
use App\Modules\Kitchen\Services\WaitTimeEstimator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Katalog publik lintas tenant (UC-01) dengan estimasi waktu tunggu per tenant (UC-11).
 * Menu habis tetap tampil nonaktif (alur 4a); tenant tutup menampilkan jam buka (alur 4b).
 * Perubahan ketersediaan oleh tenant (UC-14) diterima lewat WebSocket (channel publik
 * canteen.{id}.catalog, event CatalogChanged) sehingga tampil ≤ 5 detik tanpa memuat ulang;
 * polling 15 detik menjadi cadangan bila koneksi WebSocket terputus.
 */
new class extends Component
{
    public string $canteenSlug = '';

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $tenantId = null;

    #[Url]
    public ?string $category = null;

    public function mount(string $canteenSlug): void
    {
        $this->canteenSlug = $canteenSlug;
    }

    public function filterCategory(?string $name): void
    {
        $this->category = $name;
    }

    /**
     * Meneruskan permintaan tambah ke komponen keranjang (yang memegang sesi tepercaya).
     * Katalog sendiri anonim; ia tidak menyimpan/menentukan harga.
     */
    public function add(int $menuId): void
    {
        $this->dispatch('cart-add', menuId: $menuId);
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $canteen = $this->canteen();

        return $canteen ? ['echo:'.CatalogChanged::channelName((int) $canteen->id).',.CatalogChanged' => '$refresh'] : [];
    }

    #[Computed]
    public function canteen(): ?Canteen
    {
        return Canteen::query()->where('slug', $this->canteenSlug)->where('status', 'active')->first();
    }

    /** @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Collection<int, \App\Models\Menu>> */
    #[Computed]
    public function groups(): \Illuminate\Support\Collection
    {
        $canteen = $this->canteen();

        return $canteen
            ? app(PublicCatalogQuery::class)->browseAll($canteen, $this->search, $this->tenantId, $this->category)->groupBy('tenant_id')
            : collect();
    }

    #[Computed]
    public function tenants(): \Illuminate\Support\Collection
    {
        $canteen = $this->canteen();

        return $canteen ? app(PublicCatalogQuery::class)->activeTenants($canteen) : collect();
    }

    #[Computed]
    public function categories(): \Illuminate\Support\Collection
    {
        $canteen = $this->canteen();

        return $canteen ? app(PublicCatalogQuery::class)->categoryNames($canteen) : collect();
    }
};
?>

<div class="space-y-4" wire:poll.15s data-catalog-channel="{{ $this->canteen ? CatalogChanged::channelName((int) $this->canteen->id) : '' }}">
    @if (! $this->canteen)
        <x-empty-state title="Kantin tidak ditemukan" description="Pindai QR meja untuk membuka katalog." />
    @else
        @php($hours = app(TenantOpeningHours::class))
        @php($wait = app(WaitTimeEstimator::class))
        <div class="flex flex-col gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari menu atau tenant…"
                class="min-h-11 rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-600 dark:bg-zinc-800" />
            <select wire:model.live="tenantId" aria-label="Saring tenant"
                class="min-h-11 rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                <option value="">Semua tenant</option>
                @foreach ($this->tenants as $tenant)
                    <option value="{{ $tenant->id }}" wire:key="tenant-{{ $tenant->id }}">{{ $tenant->display_name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2 overflow-x-auto pb-1" data-test="category-chips">
                <button type="button" wire:click="filterCategory(null)" @class(['min-h-9 shrink-0 rounded-full border px-3 text-sm', 'border-red-600 bg-red-600 text-white' => ! $category, 'border-zinc-300' => $category])>Semua</button>
                @foreach ($this->categories as $name)
                    <button type="button" wire:key="chip-{{ $name }}" wire:click="filterCategory(@js($name))" @class(['min-h-9 shrink-0 rounded-full border px-3 text-sm', 'border-red-600 bg-red-600 text-white' => $category === $name, 'border-zinc-300' => $category !== $name])>{{ $name }}</button>
                @endforeach
            </div>
        </div>

        @forelse ($this->groups as $menus)
            @php($tenant = $menus->first()->tenant)
            @php($open = $hours->isOpen($tenant))
            <section wire:key="group-{{ $tenant->id }}" data-test="tenant-group">
                <div class="flex items-baseline justify-between border-b-2 border-zinc-900 pb-1 dark:border-zinc-100">
                    <h2 class="text-sm font-extrabold uppercase tracking-wide">{{ $tenant->display_name }}</h2>
                    @if ($open)
                        <span class="text-xs font-semibold text-red-600">Antrean {{ $wait->label($tenant) }}</span>
                    @else
                        <span class="text-xs font-semibold text-zinc-500">Tutup · Buka pukul {{ $hours->opensAtToday($tenant) ?? '—' }}</span>
                    @endif
                </div>
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($menus as $menu)
                        @php($sellable = $open && $menu->is_available && $menu->stock_qty > 0)
                        <li wire:key="menu-{{ $menu->id }}" @class(['flex items-center gap-3 py-3', 'opacity-50' => ! $sellable]) data-sellable="{{ $sellable ? '1' : '0' }}">
                            @if ($menu->photoUrl())
                                <img src="{{ $menu->photoUrl() }}" alt="" class="size-14 shrink-0 rounded object-cover">
                            @else
                                <span class="flex size-14 shrink-0 items-center justify-center rounded bg-zinc-200 text-sm font-bold text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ Str::upper(Str::substr($menu->name, 0, 2)) }}</span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">{{ $menu->name }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ $menu->description ? $menu->description.' · ' : '' }}± {{ $menu->prep_minutes }} mnt</p>
                                <p class="text-sm font-semibold">Rp{{ number_format($menu->base_price, 0, ',', '.') }}</p>
                            </div>
                            @unless ($menu->is_available && $menu->stock_qty > 0)
                                <span class="shrink-0 rounded border border-zinc-300 px-2 py-0.5 text-[10px] font-bold text-zinc-600">HABIS</span>
                            @endunless
                            @if ($sellable)
                                <button type="button" wire:click="add({{ $menu->id }})"
                                    class="min-h-9 shrink-0 rounded-lg border border-zinc-300 px-3 text-sm font-medium dark:border-zinc-600">
                                    + Tambah
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <x-empty-state title="Menu tidak ditemukan" description="Coba kata kunci atau kategori lain." />
        @endforelse
    @endif
</div>
