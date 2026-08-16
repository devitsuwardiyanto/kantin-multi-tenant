<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Order induk — platform-scoped (TANPA tenant_id): satu checkout bisa lintas tenant.
 */
class Order extends Model
{
    protected $fillable = ['order_number', 'checkout_key', 'status'];

    protected function casts(): array
    {
        return [
            'public_id' => 'string',
            'subtotal_amount' => 'integer',
            'tax_amount' => 'integer',
            'service_fee_amount' => 'integer',
            'grand_total_amount' => 'integer',
            'customer_snapshot' => 'array',
            'table_snapshot' => 'array',
            'placed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Canteen, $this> */
    public function canteen(): BelongsTo
    {
        return $this->belongsTo(Canteen::class);
    }

    /** @return HasMany<TenantOrder, $this> */
    public function tenantOrders(): HasMany
    {
        return $this->hasMany(TenantOrder::class);
    }
}
