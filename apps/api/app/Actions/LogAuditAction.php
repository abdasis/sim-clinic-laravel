<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Catat audit log (spec 001, FR-028) via spatie/laravel-activitylog.
 * Dipakai lintas story: tenant.registered, user.login, staff.created, dst.
 *
 * Pemetaan: $action -> event + log_name, $description -> description (narasi),
 * $tenant -> properties->tenant_id (docs/erd/audit_logs.md).
 */
class LogAuditAction
{
    public function handle(
        string $action,
        ?Model $subject = null,
        ?User $causer = null,
        array $context = [],
        ?string $description = null,
        ?Tenant $tenant = null,
    ): ?Activity {
        $tenant ??= app()->bound('tenant') ? app('tenant') : null;
        $causer ??= auth()->user();

        if ($tenant !== null && ! array_key_exists('tenant_id', $context)) {
            $context['tenant_id'] = $tenant->id;
        }

        $logger = activity($this->logName($action))
            ->event($action)
            ->causedBy($causer)
            ->withProperties($context);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $activity = $logger->log($description ?? $this->fallbackDescription($action, $subject));

        return $activity instanceof Activity ? $activity : null;
    }

    /**
     * Namespace modul diambil dari prefix action (user.login -> user).
     */
    private function logName(string $action): ?string
    {
        return str_contains($action, '.') ? strtok($action, '.') : null;
    }

    /**
     * Narasi minimal saat caller tidak menyediakan deskripsi, agar log
     * tidak pernah kosong/robotik.
     */
    private function fallbackDescription(string $action, ?Model $subject): string
    {
        $label = $subject ? class_basename($subject) : 'Sistem';
        $id = $subject?->getKey();

        $name = $subject?->name
            ?? $subject?->email
            ?? $subject?->company_name;

        if ($name !== null) {
            return sprintf('Tindakan %s pada %s "%s" (id: %s).', $action, $label, $name, $id);
        }

        return sprintf('Tindakan %s pada %s (id: %s).', $action, $label, $id);
    }
}
