<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope untuk isolasi data per tenant (spec 001, FR-012).
 * Membaca tenant aktif dari container ('tenant'). Jika tidak ada tenant
 * aktif (mis. CLI/seeding/central), scope tidak memfilter apa pun.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('tenant')) {
            return;
        }

        $tenant = app('tenant');

        if ($tenant === null) {
            return;
        }

        $builder->where($model->getTable().'.tenant_id', $tenant->id);
    }
}
