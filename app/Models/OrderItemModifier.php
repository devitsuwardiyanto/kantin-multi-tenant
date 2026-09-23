<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot modifier terpilih pada satu order item (append-only, historis). Nama & delta
 * harga dibekukan saat checkout; ref group/option hanya penunjuk (bukan sumber harga).
 */
class OrderItemModifier extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'group_name_snapshot', 'option_name_snapshot', 'price_delta_snapshot',
    ];

    protected function casts(): array
    {
        return ['price_delta_snapshot' => 'integer'];
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
