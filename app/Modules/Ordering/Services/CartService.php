<?php

namespace App\Modules\Ordering\Services;

use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\ModifierOption;
use App\Modules\Ordering\Data\CartLine;
use App\Modules\Ordering\Data\CartView;
use App\Modules\Ordering\Exceptions\CartException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Redis;

/**
 * Keranjang pelanggan yang tersimpan di Redis, di-key per SESI pelanggan (ULID dari cookie
 * HttpOnly yang di-hash, bukan input klien). Prinsip keamanan inti:
 *
 *  - Redis hanya menyimpan IDENTITAS + kuantitas (menu_id, tenant_id, modifier ids). Harga
 *    TIDAK PERNAH dipercaya dari keranjang; selalu dihitung ulang dari basis data di view().
 *  - Menu wajib milik tenant AKTIF dari canteen sesi. Injeksi menu lintas-canteen/tenant
 *    ditolak di add() (query ter-scope canteen).
 *  - Hanya key milik sesi yang disentuh (SETEX/DEL satu key). Tidak ada FLUSHDB / hapus global.
 *
 * @phpstan-type CartItem array{line_key: string, menu_id: int, tenant_id: int, quantity: int, modifier_option_ids: list<int>, price_at_add: int, name_at_add: string, tenant_name_at_add: string}
 */
final class CartService
{
    private const KEY_PREFIX = 'cart:';

    private const MAX_QTY_PER_LINE = 20;

    private const MAX_LINES = 50;

    private const FALLBACK_TTL_SECONDS = 14400; // 4 jam, selaras masa hidup sesi pelanggan

    /**
     * Menambah menu (opsional dengan modifier) ke keranjang. Menggabungkan baris dengan
     * konfigurasi identik. Menolak menu/modifier yang tidak sah untuk kantin sesi ini.
     *
     * @param  list<int>  $modifierOptionIds
     */
    public function add(CustomerSession $session, int $menuId, int $quantity = 1, array $modifierOptionIds = []): void
    {
        if ($quantity < 1) {
            throw CartException::invalidQuantity();
        }

        $menu = $this->orderableMenu($session, $menuId);
        if ($menu === null) {
            throw CartException::menuNotOrderable();
        }

        $modifierIds = $this->normalizeModifierIds($modifierOptionIds);
        $options = $this->validModifierOptions($menu, $modifierIds);

        $lineKey = $this->lineKey($menuId, $modifierIds);
        $items = $this->load($session);

        $existingQty = isset($items[$lineKey]) ? (int) $items[$lineKey]['quantity'] : 0;
        $newQty = min($existingQty + $quantity, self::MAX_QTY_PER_LINE);

        if (! isset($items[$lineKey]) && count($items) >= self::MAX_LINES) {
            throw CartException::tooManyLines();
        }

        $modifierTotal = $options->sum('price_delta');

        $items[$lineKey] = [
            'line_key' => $lineKey,
            'menu_id' => $menu->id,
            'tenant_id' => $menu->tenant_id,
            'quantity' => $newQty,
            'modifier_option_ids' => $modifierIds,
            'price_at_add' => (int) $menu->base_price + (int) $modifierTotal,
            'name_at_add' => (string) $menu->name,
            'tenant_name_at_add' => (string) $menu->tenant->display_name,
        ];

        $this->persist($session, $items);
    }

    /**
     * Menyetel kuantitas sebuah baris. 0 (atau kurang) menghapus baris.
     */
    public function setQuantity(CustomerSession $session, string $lineKey, int $quantity): void
    {
        $items = $this->load($session);
        if (! isset($items[$lineKey])) {
            return;
        }

        if ($quantity < 1) {
            unset($items[$lineKey]);
        } else {
            $items[$lineKey]['quantity'] = min($quantity, self::MAX_QTY_PER_LINE);
        }

        $this->persist($session, $items);
    }

    public function remove(CustomerSession $session, string $lineKey): void
    {
        $items = $this->load($session);
        if (! isset($items[$lineKey])) {
            return;
        }

        unset($items[$lineKey]);
        $this->persist($session, $items);
    }

    public function clear(CustomerSession $session): void
    {
        Redis::del($this->key($session));
    }

    /**
     * Menyusun tampilan keranjang TERREVALIDASI: harga & stok dihitung ulang dari basis data,
     * baris yang tak lagi dapat dipesan ditandai dan dikeluarkan dari subtotal.
     */
    public function view(CustomerSession $session): CartView
    {
        $items = $this->load($session);
        if ($items === []) {
            return new CartView([], 0, 0, false);
        }

        $menus = Menu::query()
            ->withoutGlobalScope('tenant')
            ->with('tenant:id,display_name,status,canteen_id')
            ->whereIn('id', array_column($items, 'menu_id'))
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = 0;
        $totalQuantity = 0;
        $blocking = false;

        foreach ($items as $item) {
            $line = $this->revalidateLine($session, $item, $menus->get($item['menu_id']));
            $lines[] = $line;
            $totalQuantity += $line->quantity;

            if ($line->available) {
                $subtotal += $line->lineTotal;
            } else {
                $blocking = true;
            }
        }

        return new CartView($lines, $subtotal, $totalQuantity, $blocking);
    }

    /**
     * @param  array<string, mixed>  $item  data mentah dari Redis (tak bertipe; setiap nilai di-cast eksplisit)
     */
    private function revalidateLine(CustomerSession $session, array $item, ?Menu $menu): CartLine
    {
        $quantity = (int) $item['quantity'];
        $issues = [];
        $available = true;

        $tenantActive = $menu !== null
            && $menu->tenant !== null
            && $menu->tenant->canteen_id === $session->canteen_id
            && $menu->tenant->status === 'active';

        if ($menu === null || ! $tenantActive || ! $menu->is_available) {
            $issues[] = 'menu_unavailable';
            $available = false;
        }

        // Modifier saat ini (harga & ketersediaan otoritatif dari DB).
        $storedModifierIds = is_array($item['modifier_option_ids'] ?? null) ? $item['modifier_option_ids'] : [];
        $modifierIds = $this->normalizeModifierIds($storedModifierIds);
        $options = $modifierIds === [] || $menu === null
            ? collect()
            : ModifierOption::query()
                ->withoutGlobalScope('tenant')
                ->with('group:id,name')
                ->where('tenant_id', $menu->tenant_id)
                ->whereIn('id', $modifierIds)
                ->get();

        if (count($options) !== count($modifierIds) || $options->contains(fn (ModifierOption $o): bool => ! $o->is_available)) {
            if ($modifierIds !== []) {
                $issues[] = 'modifier_unavailable';
                $available = false;
            }
        }

        $unitPrice = (int) ($menu->base_price ?? 0);
        $modifierTotal = (int) $options->sum('price_delta');

        if ($available && (int) $menu->stock_qty < $quantity) {
            $issues[] = 'insufficient_stock';
            $available = false;
        }

        if ($menu !== null && (int) $item['price_at_add'] !== $unitPrice + $modifierTotal) {
            $issues[] = 'price_changed';
        }

        $lineTotal = $available ? ($unitPrice + $modifierTotal) * $quantity : 0;

        return new CartLine(
            lineKey: (string) $item['line_key'],
            menuId: (int) $item['menu_id'],
            tenantId: (int) $item['tenant_id'],
            tenantName: $menu?->tenant->display_name ?? (string) $item['tenant_name_at_add'],
            name: $menu->name ?? (string) $item['name_at_add'],
            quantity: $quantity,
            unitPrice: $unitPrice,
            priceAtAdd: (int) $item['price_at_add'],
            prepMinutes: (int) ($menu->prep_minutes ?? 0),
            modifiers: array_values($options->map(fn (ModifierOption $o): array => [
                'id' => $o->id,
                'group_id' => (int) $o->group_id,
                'group_name' => (string) $o->group->name,
                'name' => (string) $o->name,
                'price_delta' => (int) $o->price_delta,
            ])->all()),
            modifierTotal: $modifierTotal,
            lineTotal: $lineTotal,
            available: $available,
            issues: $issues,
        );
    }

    /**
     * Menu yang sah untuk dipesan pelanggan ini: milik tenant AKTIF pada canteen sesi,
     * ditandai tersedia, dan berstok. Bypass global scope diganti filter canteen eksplisit.
     */
    private function orderableMenu(CustomerSession $session, int $menuId): ?Menu
    {
        return Menu::query()
            ->withoutGlobalScope('tenant')
            ->with('tenant:id,display_name,status,canteen_id')
            ->where('id', $menuId)
            ->where('is_available', true)
            ->where('stock_qty', '>', 0)
            ->whereHas('tenant', function ($query) use ($session): void {
                $query->where('canteen_id', $session->canteen_id)->where('status', 'active');
            })
            ->first();
    }

    /**
     * Opsi modifier yang sah untuk menu: milik tenant yang sama & tersedia. Menolak injeksi
     * modifier lintas-tenant.
     *
     * @param  list<int>  $modifierIds
     * @return Collection<int, ModifierOption>
     */
    private function validModifierOptions(Menu $menu, array $modifierIds): Collection
    {
        if ($modifierIds === []) {
            /** @var Collection<int, ModifierOption> $empty */
            $empty = ModifierOption::query()->whereRaw('1 = 0')->get();

            return $empty;
        }

        $options = ModifierOption::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $menu->tenant_id)
            ->where('is_available', true)
            ->whereIn('id', $modifierIds)
            ->get();

        if (count($options) !== count($modifierIds)) {
            throw CartException::modifierNotOrderable();
        }

        return $options;
    }

    /**
     * @param  list<int>  $modifierIds
     */
    private function lineKey(int $menuId, array $modifierIds): string
    {
        return substr(hash('sha256', $menuId.'|'.implode(',', $modifierIds)), 0, 24);
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private function normalizeModifierIds(array $ids): array
    {
        $normalized = array_values(array_unique(array_map('intval', $ids)));
        sort($normalized);

        return $normalized;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function load(CustomerSession $session): array
    {
        $raw = Redis::get($this->key($session));
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['items']) || ! is_array($decoded['items'])) {
            return [];
        }

        $items = [];
        foreach ($decoded['items'] as $item) {
            if (is_array($item) && isset($item['line_key'])) {
                $items[(string) $item['line_key']] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     */
    private function persist(CustomerSession $session, array $items): void
    {
        if ($items === []) {
            Redis::del($this->key($session));

            return;
        }

        $payload = json_encode(['items' => array_values($items)], JSON_THROW_ON_ERROR);
        Redis::setex($this->key($session), $this->ttlSeconds($session), $payload);
    }

    private function ttlSeconds(CustomerSession $session): int
    {
        if ($session->expires_at !== null) {
            $remaining = now()->diffInSeconds($session->expires_at, false);
            if ($remaining > 0) {
                return (int) $remaining;
            }
        }

        return self::FALLBACK_TTL_SECONDS;
    }

    private function key(CustomerSession $session): string
    {
        return self::KEY_PREFIX.$session->getKey();
    }
}
