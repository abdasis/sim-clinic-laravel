<?php

namespace App\Policies;

use App\Models\User;

/**
 * Patient: Admin/Dokter/Kasir = CRUD; Terapis = view only (R2 matriks).
 */
class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('patient.view');
    }

    public function view(User $user): bool
    {
        return $user->can('patient.view');
    }

    public function create(User $user): bool
    {
        return $user->can('patient.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('patient.manage');
    }
}
