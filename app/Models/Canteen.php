<?php

namespace App\Models;

use Database\Factories\CanteenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Canteen extends Model
{
    /** @use HasFactory<CanteenFactory> */
    use HasFactory;

    protected $fillable = ['code', 'slug', 'name', 'tax_rate', 'service_fee_rate', 'status'];

    protected function casts(): array
    {
        return ['tax_rate' => 'decimal:4', 'service_fee_rate' => 'decimal:4'];
    }

    /** @return HasMany<Tenant, $this> */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /** @return HasMany<DiningTable, $this> */
    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }
}
