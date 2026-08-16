<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CommissionSchemeFactory;
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

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
