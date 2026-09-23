<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Database\Factories\CommissionSchemeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionScheme extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CommissionSchemeFactory> */
    use HasFactory;

    protected $fillable = ['commission_rate', 'valid_from', 'valid_to'];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:4',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    /**
     * Skema yang berlaku pada instan tertentu. Periode memakai interval setengah-terbuka
     * [valid_from, valid_to): valid_to adalah instan pertama skema TIDAK berlaku lagi, yakni
     * valid_from versi penggantinya. Tidak ada celah maupun tumpang tindih di batas pergantian,
     * berapa pun presisi timestamp-nya.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function effectiveAt(Builder $query, CarbonInterface $instant): void
    {
        $query->where('valid_from', '<=', $instant)
            ->where(function (Builder $query) use ($instant): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>', $instant);
            });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
