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
        return $user->can('clinic.access', ['medical_record', 'r']);
    }

    public function view(User $user): bool
    {
        return $user->can('clinic.access', ['medical_record', 'r']);
    }

    public function create(User $user): bool
    {
        return $user->can('clinic.access', ['medical_record', 'w']);
    }

    public function update(User $user): bool
    {
        return $user->can('clinic.access', ['medical_record', 'w']);
    }
}
