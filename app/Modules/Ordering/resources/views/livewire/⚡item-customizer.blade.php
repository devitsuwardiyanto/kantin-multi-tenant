<?php

use App\Models\CustomerSession;
use App\Models\Menu;
use App\Modules\Ordering\Exceptions\CartException;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\ResolveCustomerSession;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * UC-04 Kustomisasi Item Pesanan (extend UC-03). Formulir modifier dibuka oleh komponen
 * keranjang untuk menu yang memiliki grup modifier aktif. Harga yang tampil hanya pratinjau;
 * CartService::add() memvalidasi ulang min/maks per grup, ketersediaan opsi, dan catatan.
 */
new class extends Component
{
    public ?int $menuId = null;

    /** @var array<int|string, int|string|null> grup pilih-satu: group_id => option_id */
    public array $single = [];

    /** @var array<int|string, array<int, int|string>> grup pilih-banyak: group_id => [option_id] */
    public array $multi = [];

    public string $note = '';

    public int $quantity = 1;

    private function session(): ?CustomerSession
    {
        return app(ResolveCustomerSession::class)->current(request());
    }

    #[On('customize-item')]
    public function open(int $menuId): void
    {
        $this->reset(['single', 'multi', 'note', 'quantity']);
        $this->resetErrorBag();
        $this->menuId = $menuId;
        unset($this->menu, $this->groups);

        // Checkbox Livewire hanya terikat sebagai array bila nilai awalnya array; tanpa ini
        // satu checkbox dianggap boolean dan mencentang seluruh opsi grup.
        foreach ($this->groups as $group) {
            if ($group->max_select > 1) {
                $this->multi[$group->id] = [];
            }
        }
    }

    public function close(): void
    {
        $this->menuId = null;
    }

    #[Computed]
    public function menu(): ?Menu
    {
        return $this->menuId === null ? null : Menu::query()->withoutGlobalScope('tenant')
            ->with('tenant:id,display_name')->find($this->menuId);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ModifierGroup> */
    #[Computed]
    public function groups(): \Illuminate\Database\Eloquent\Collection
    {
        $session = $this->session();

        return $session === null || $this->menuId === null
            ? new \Illuminate\Database\Eloquent\Collection
            : app(CartService::class)->modifierGroupsFor($session, $this->menuId);
    }

    /** @return list<int> */
    private function selectedOptionIds(): array
    {
        $ids = array_filter($this->single, fn ($id): bool => $id !== null && $id !== '');
        foreach ($this->multi as $optionIds) {
            if (is_array($optionIds)) {
                array_push($ids, ...array_values($optionIds));
            }
        }

        return array_values(array_map('intval', $ids));
    }

    /** UC-04 langkah 4 (pratinjau): harga dasar + harga seluruh modifier terpilih. */
    #[Computed]
    public function unitPrice(): int
    {
        $selected = $this->selectedOptionIds();
        $delta = $this->groups->flatMap->options->whereIn('id', $selected)->sum('price_delta');

        return (int) ($this->menu->base_price ?? 0) + (int) $delta;
    }

    public function increment(): void
    {
        $this->quantity = min($this->quantity + 1, 20);
    }

    public function decrement(): void
    {
        $this->quantity = max($this->quantity - 1, 1);
    }

    /** UC-04 langkah 5: simpan konfigurasi modifier bersama item pada keranjang. */
    public function confirm(): void
    {
        $this->validate(['note' => ['nullable', 'string', 'max:'.CartService::NOTE_MAX_LENGTH]], [], ['note' => 'catatan khusus']);

        $session = $this->session();
        if ($session === null || $this->menuId === null) {
            return;
        }

        try {
            app(CartService::class)->add($session, $this->menuId, $this->quantity, $this->selectedOptionIds(), $this->note);
        } catch (CartException $e) {
            $this->addError('customizer', $e->getMessage());

            return;
        }

        $this->close();
        $this->dispatch('cart-updated');
    }
};
?>

<div>
    @if ($menuId !== null && $this->menu)
        <div class="fixed inset-0 z-40 flex items-end justify-center bg-black/50 sm:items-center"
            x-data x-on:keydown.escape.window="$wire.close()">
            <div role="dialog" aria-modal="true" aria-labelledby="customizer-title"
                class="max-h-[92vh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-white dark:bg-zinc-900 sm:rounded-2xl">
                <div class="flex items-start justify-between border-b-2 border-zinc-900 p-4 dark:border-zinc-100">
                    <div>
                        <h2 id="customizer-title" class="text-xl font-extrabold">{{ $this->menu->name }}</h2>
                        <p class="text-sm text-zinc-500">{{ $this->menu->tenant->display_name }} · harga dasar Rp{{ number_format($this->menu->base_price, 0, ',', '.') }}</p>
                    </div>
                    <button type="button" wire:click="close" class="min-h-9 min-w-9 text-2xl leading-none" aria-label="Tutup">×</button>
                </div>

                <div class="space-y-5 p-4">
                    @foreach ($this->groups as $group)
                        <fieldset wire:key="group-{{ $group->id }}" data-test="modifier-group">
                            <legend class="mb-2 flex w-full items-center justify-between">
                                <span class="text-sm font-extrabold uppercase tracking-wide">{{ $group->name }}</span>
                                @if ($group->min_select > 0)
                                    <span class="rounded bg-red-600 px-2 py-0.5 text-[11px] font-bold text-white">WAJIB · PILIH {{ $group->min_select === $group->max_select ? $group->min_select : $group->min_select.'–'.$group->max_select }}</span>
                                @else
                                    <span class="text-xs text-zinc-500">opsional, maks {{ $group->max_select }}</span>
                                @endif
                            </legend>
                            <div class="space-y-2">
                                @foreach ($group->options as $option)
                                    <label wire:key="option-{{ $option->id }}" @class([
                                        'flex min-h-12 items-center gap-3 rounded-lg border px-3',
                                        'border-zinc-900 dark:border-zinc-100' => $option->is_available,
                                        'cursor-not-allowed border-zinc-200 text-zinc-400 dark:border-zinc-700' => ! $option->is_available,
                                    ])>
                                        @if ($group->max_select === 1)
                                            <input type="radio" wire:model.live="single.{{ $group->id }}" value="{{ $option->id }}" @disabled(! $option->is_available) class="size-5 accent-red-600">
                                        @else
                                            <input type="checkbox" wire:model.live="multi.{{ $group->id }}" value="{{ $option->id }}" @disabled(! $option->is_available) class="size-5 accent-red-600">
                                        @endif
                                        <span class="flex-1 font-semibold">{{ $option->name }}</span>
                                        @if ($option->is_available)
                                            <span class="text-sm font-semibold">+Rp{{ number_format($option->price_delta, 0, ',', '.') }}</span>
                                        @else
                                            <span class="rounded border border-zinc-300 px-2 py-0.5 text-[10px] font-bold">HABIS</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div>
                        <label for="customizer-note" class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-extrabold uppercase tracking-wide">Catatan khusus</span>
                            <span class="text-xs text-zinc-500">{{ mb_strlen($note) }}/200</span>
                        </label>
                        <input id="customizer-note" type="text" wire:model.live.debounce.300ms="note" maxlength="200"
                            placeholder="Mis. sambal terpisah"
                            class="min-h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                        @error('note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @error('customizer')
                        <p class="rounded-lg bg-red-100 px-3 py-2 text-sm font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sticky bottom-0 flex gap-3 border-t-2 border-zinc-900 bg-white p-4 dark:border-zinc-100 dark:bg-zinc-900">
                    <div class="flex border-2 border-zinc-900 dark:border-zinc-100">
                        <button type="button" wire:click="decrement" class="min-h-11 min-w-11 text-xl font-bold" aria-label="Kurangi jumlah">−</button>
                        <span class="flex min-w-11 items-center justify-center border-x-2 border-zinc-900 font-bold dark:border-zinc-100">{{ $quantity }}</span>
                        <button type="button" wire:click="increment" class="min-h-11 min-w-11 text-xl font-bold" aria-label="Tambah jumlah">+</button>
                    </div>
                    <button type="button" wire:click="confirm" class="flex min-h-11 flex-1 items-center justify-between bg-red-600 px-4 font-bold text-white">
                        <span>Tambah</span>
                        <span>Rp{{ number_format($this->unitPrice * $quantity, 0, ',', '.') }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
