<?php

namespace App\Models;

use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    protected $fillable = ['canteen_id', 'code', 'label', 'zone', 'status'];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** @return BelongsTo<Canteen, $this> */
    public function canteen(): BelongsTo
    {
        return $this->belongsTo(Canteen::class);
    }

    /** @return HasMany<TableQrToken, $this> */
    public function qrTokens(): HasMany
    {
        return $this->hasMany(TableQrToken::class);
    }

    /** @return HasOne<TableQrToken, $this> */
    public function activeToken(): HasOne
    {
        return $this->hasOne(TableQrToken::class)->active()->latestOfMany();
    }
}
