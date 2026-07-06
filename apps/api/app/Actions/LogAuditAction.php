<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Catat audit log (spec 001, FR-028).
 * Dipakai lintas story: tenant.registered, user.login, staff.created, dst.
 */
class LogAuditAction
{
    public function handle(
        string $action,
        ?Model $subject = null,
        ?User $causer = null,
        array $context = [],
        ?Tenant $tenant = null,
    ): AuditLog {
        $tenant ??= app()->bound('tenant') ? app('tenant') : null;
        $causer ??= auth()->user();

        return AuditLog::create([
            'tenant_id' => $tenant?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'causer_id' => $causer?->id,
            'properties' => $context,
        ]);
    }
}
