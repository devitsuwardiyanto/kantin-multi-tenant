<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penarikan dana tenant: requested → paid | rejected; `investigation` bila ledger tidak sesuai
 * (UC-23 alur 3a) — dana tetap ditahan dan tidak dapat disetujui.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $bank_account_id
 * @property int $amount
 * @property string $status
 * @property string|null $review_note
 * @property string|null $transfer_proof_path
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $created_at
 */
class Withdrawal extends Model
{
    use BelongsToTenant;

    /** Status yang masih memegang active_tenant_lock (dana tertahan). */
    public const ACTIVE_STATUSES = ['requested', 'investigation'];

    protected $fillable = ['amount', 'status'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'transfer_snapshot' => 'array', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<TenantBankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(TenantBankAccount::class, 'bank_account_id');
    }

    /** Nomor referensi tampilan, mis. WD-260718-0007 (tanggal WIB + id). */
    public function reference(): string
    {
        return self::referenceFor($this->id, (string) $this->created_at?->toDateTimeString());
    }

    public static function referenceFor(int $id, string $createdAtUtc): string
    {
        $date = CarbonImmutable::parse($createdAtUtc, (string) config('app.timezone'))->setTimezone((string) config('app.display_timezone'));

        return 'WD-'.$date->format('ymd').'-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }
}
