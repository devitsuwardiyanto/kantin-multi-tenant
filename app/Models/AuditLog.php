<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak audit append-only. Tidak di-update; koreksi = event baru.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'actor_id', 'canteen_id', 'tenant_id', 'entity', 'entity_id',
        'action', 'request_id', 'before', 'after', 'metadata', 'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'metadata' => 'array',
            'logged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
