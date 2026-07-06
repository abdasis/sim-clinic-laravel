<?php

namespace App\Concerns;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait untuk model tenant-scopeable (spec 001).
 * - Menerapkan TenantScope global.
 * - Mengisi tenant_id otomatis saat creating dari tenant aktif container.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if ($model->tenant_id === null && app()->bound('tenant') && app('tenant') !== null) {
                $model->tenant_id = app('tenant')->id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
