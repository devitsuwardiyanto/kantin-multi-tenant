<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ModifierGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierGroup extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ModifierGroupFactory> */
    use HasFactory;

    protected $fillable = ['name', 'min_select', 'max_select', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'min_select' => 'integer', 'max_select' => 'integer'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<ModifierOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class, 'group_id');
    }
}
