<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Menu extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    // tenant_id & category_id di-set eksplisit/aturan domain, bukan mass assignment pelanggan.
    protected $fillable = ['name', 'base_price', 'stock_qty', 'is_available', 'prep_minutes'];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'stock_qty' => 'integer',
            'is_available' => 'boolean',
            'prep_minutes' => 'integer',
        ];
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
}
