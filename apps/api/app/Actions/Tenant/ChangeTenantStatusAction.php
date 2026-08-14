<?php

namespace App\Actions\Tenant;

use App\Actions\LogAuditAction;
use App\Enums\TenantStatus;
use App\Models\Tenant;

/**
 * Aktifkan atau nonaktifkan tenant dari panel platform. Tenant nonaktif
 * tidak bisa diakses siapa pun sampai diaktifkan lagi.
 */
class ChangeTenantStatusAction
{
    public function handle(Tenant $tenant, TenantStatus $status): Tenant
    {
        $old = $tenant->status;

        $tenant->update(['status' => $status]);

        app(LogAuditAction::class)->handle(
            'tenant.status_changed',
            $tenant,
            auth()->user(),
            ['old' => ['status' => $old->value], 'new' => ['status' => $status->value]],
            'Status tenant '.$tenant->name.' diubah dari '.$old->label().' ke '.$status->label().'.',
            $tenant,
        );

        return $tenant;
    }
}
