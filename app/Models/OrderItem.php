<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name_snapshot', 'unit_price_snapshot', 'prep_minutes_snapshot', 'quantity', 'modifier_total', 'line_total'];

    protected function casts(): array
    {
        return [
            'unit_price_snapshot' => 'integer',
            'prep_minutes_snapshot' => 'integer',
            'quantity' => 'integer',
            'modifier_total' => 'integer',
            'line_total' => 'integer',
        ];
    }

    /** @return BelongsTo<TenantOrder, $this> */
    public function tenantOrder(): BelongsTo
    {
        return $this->belongsTo(TenantOrder::class);
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /** @return HasMany<OrderItemModifier, $this> */
    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }
}
