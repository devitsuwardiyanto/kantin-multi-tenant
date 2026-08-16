<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MenuCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<MenuCategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<Menu, $this> */
    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class, 'category_id');
    }
}
