<?php

namespace App\Services;

use App\Actions\Tenant\ChangeTenantStatusAction;
use App\Enums\TenantStatus;
use App\Models\Tenant;

/**
 * Use case pengelolaan tenant oleh admin platform.
 */
class PlatformTenantService
{
    public function changeStatus(Tenant $tenant, string $status): Tenant
    {
        return app(ChangeTenantStatusAction::class)->handle($tenant, TenantStatus::from($status));
    }
}
