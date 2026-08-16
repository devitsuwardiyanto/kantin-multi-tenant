<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = ['status', 'scheduled_at'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'commission_rate_snapshot' => 'decimal:4',
            'subtotal_amount' => 'integer',
            'tax_amount' => 'integer',
            'service_fee_amount' => 'integer',
            'commission_amount' => 'integer',
            'net_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
