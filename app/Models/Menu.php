<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Menu extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    // UC-13 alur 3a: menu yang dihapus tetap ada untuk riwayat transaksi.
    use SoftDeletes;

    // tenant_id & category_id di-set eksplisit/aturan domain, bukan mass assignment pelanggan.
    protected $fillable = ['name', 'description', 'base_price', 'stock_qty', 'is_available', 'prep_minutes'];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'stock_qty' => 'integer',
            'is_available' => 'boolean',
            'prep_minutes' => 'integer',
        ];
    }

    /** URL publik foto WebP, atau null bila belum ada foto. */
    public function photoUrl(): ?string
    {
        return $this->photo_path !== null ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<MenuCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    /** @return BelongsToMany<ModifierGroup, $this> */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'menu_modifier_groups', 'menu_id', 'modifier_group_id')
            ->withPivot(['tenant_id', 'sort_order'])
            ->orderBy('menu_modifier_groups.sort_order');
    }

    /**
     * Predikat sellable: tersedia manual dan stok memadai. Tenant aktif difilter di query publik.
     *
     * @param  Builder<Menu>  $query
     */
    public function scopeSellable(Builder $query): void
    {
        $query->where('is_available', true)->where('stock_qty', '>', 0);
    }
}
