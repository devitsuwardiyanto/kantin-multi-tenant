<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBalance extends Model
{
    protected $primaryKey = 'tenant_id';

    public $incrementing = false;

    protected $fillable = ['available_amount', 'held_amount'];

    protected function casts(): array
    {
        return ['available_amount' => 'integer', 'held_amount' => 'integer'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
