<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Model tenant-owned: menambahkan global scope tenant_id dan mengisi tenant_id saat create
 * dari TenantContext aktif. Auto-fill membantu konsistensi, tetapi policy + composite FK
 * database tetap wajib (pertahanan berlapis). tenant_id TIDAK boleh mass-assignable.
 *
 * Catatan: scope hanya memfilter saat context terisi. Route internal SELALU mengisi context
 * (SetTenantContext); alur lintas-tenant yang sah memakai withoutGlobalScope('tenant') + filter
 * canteen/status eksplisit. Write tanpa context gagal via NOT NULL tenant_id di DB (fail-closed).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $context = app(TenantContext::class);
            if ($context->has()) {
                $builder->where($builder->qualifyColumn('tenant_id'), $context->id());
            }
        });

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);
            if ($context->has() && empty($model->getAttribute('tenant_id'))) {
                $model->setAttribute('tenant_id', $context->id());
            }
        });
    }
}
