<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jam operasional tenant per hari (0 = Minggu … 6 = Sabtu), waktu lokal kantin (WIB).
 * Tenant tanpa baris jam dianggap buka sepanjang waktu (belum dikonfigurasi).
 */
class TenantOperatingHour extends Model
{
    use BelongsToTenant;

    protected $fillable = ['day_of_week', 'opens_at', 'closes_at'];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
