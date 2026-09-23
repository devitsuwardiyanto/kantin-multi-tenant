<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    // canteen_id di-set eksplisit (bukan dari request pelanggan).
    protected $fillable = ['code', 'slug', 'display_name', 'status'];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** @return BelongsTo<Canteen, $this> */
    public function canteen(): BelongsTo
    {
        return $this->belongsTo(Canteen::class);
    }

    /** @return HasOne<TenantBalance, $this> */
    public function balance(): HasOne
    {
        return $this->hasOne(TenantBalance::class);
    }

    /** @return HasMany<Menu, $this> */
    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    /** @return HasMany<CommissionScheme, $this> */
    public function commissionSchemes(): HasMany
    {
        return $this->hasMany(CommissionScheme::class);
    }

    /** @return HasMany<TenantBankAccount, $this> */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(TenantBankAccount::class);
    }

    /** @return HasMany<UserTenantRole, $this> */
    public function tenantRoles(): HasMany
    {
        return $this->hasMany(UserTenantRole::class);
    }
}
