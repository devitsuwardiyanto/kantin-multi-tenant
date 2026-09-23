<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keanggotaan user pada canteen (pengelola). Role: owner|manager|finance|viewer.
 */
class UserCanteenRole extends Model
{
    protected $fillable = ['user_id', 'canteen_id', 'role'];

    /** @return BelongsTo<Canteen, $this> */
    public function canteen(): BelongsTo
    {
        return $this->belongsTo(Canteen::class);
    }
}
