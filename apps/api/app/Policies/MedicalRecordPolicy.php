<?php

namespace App\Policies;

use App\Models\User;

/**
 * Rekam Medis: Admin/Dokter/Terapis = rw; Kasir = ditolak (FR-044).
 */
class MedicalRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('medical_record.view');
    }

    public function view(User $user): bool
    {
        return $user->can('medical_record.view');
    }

    public function create(User $user): bool
    {
        return $user->can('medical_record.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('medical_record.manage');
    }
}
