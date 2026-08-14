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
        ?string $description = null,
        ?Tenant $tenant = null,
    ): AuditLog {
        $tenant ??= app()->bound('tenant') ? app('tenant') : null;
        $causer ??= auth()->user();

        return AuditLog::create([
            'tenant_id' => $tenant?->id,
            'action' => $action,
            'description' => $description ?? $this->fallbackDescription($action, $subject),
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'causer_id' => $causer?->id,
            'properties' => $context,
        ]);
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
