<?php

namespace App\Models;

use Database\Factories\ModifierOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierOption extends Model
{
    /** @use HasFactory<ModifierOptionFactory> */
    use HasFactory;

    protected $fillable = ['name', 'price_delta', 'stock_qty', 'is_available'];

    protected function casts(): array
    {
        return ['price_delta' => 'integer', 'stock_qty' => 'integer', 'is_available' => 'boolean'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<ModifierGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'group_id');
    }
}
