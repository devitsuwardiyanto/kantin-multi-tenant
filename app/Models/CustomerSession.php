<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sesi pelanggan anonim (PK ULID). Terikat canteen + meja hasil scan QR yang valid.
 * session_token_hash & foreign key di-set service; cookie menyimpan token mentah.
 *
 * @property int $canteen_id
 * @property int $dining_table_id
 * @property string $status
 * @property CarbonImmutable|null $expires_at
 */
class CustomerSession extends Model
{
    use HasUlids;

    protected $fillable = ['status'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** @return BelongsTo<Canteen, $this> */
    public function canteen(): BelongsTo
    {
        return $this->belongsTo(Canteen::class);
    }

    /** @return BelongsTo<DiningTable, $this> */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
