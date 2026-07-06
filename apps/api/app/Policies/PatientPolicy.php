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
        return $user->can('clinic.access', ['patient', 'r']);
    }

    public function view(User $user): bool
    {
        return $user->can('clinic.access', ['patient', 'r']);
    }

    public function create(User $user): bool
    {
        return $user->can('clinic.access', ['patient', 'w']);
    }

    public function update(User $user): bool
    {
        return $user->can('clinic.access', ['patient', 'w']);
    }
}
