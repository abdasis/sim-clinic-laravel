<?php

namespace App\Policies;

use App\Models\User;

/**
 * Report: Admin only (FR-075). Reports tidak punya Eloquent model — controller
 * meng-authorize via Gate clinic.access langsung; policy ini untuk kelengkapan.
 */
class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clinic.access', ['report', 'r']);
    }
}
