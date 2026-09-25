<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan ekspor laporan penjualan tenant (UC-17). Berkas dibuat job queue lalu
 * tersedia sampai expires_at (24 jam).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $requested_by
 * @property string $format
 * @property CarbonImmutable $date_from
 * @property CarbonImmutable $date_to
 * @property string $status
 * @property int $row_count
 * @property bool $notify_by_mail
 * @property string|null $path
 * @property CarbonImmutable|null $expires_at
 */
class ReportExport extends Model
{
    use BelongsToTenant;

    protected $fillable = ['format', 'date_from', 'date_to'];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'row_count' => 'integer',
            'notify_by_mail' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'ready' && $this->path !== null && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
