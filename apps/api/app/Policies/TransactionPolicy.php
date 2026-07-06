<?php

namespace App\Policies;

use App\Models\User;

/**
 * Transaction (POS): Admin = rw; Kasir = rw; lainnya ditolak (R2 matriks).
 */
class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clinic.access', ['transaction', 'r']);
    }

    public function view(User $user): bool
    {
        return $user->can('clinic.access', ['transaction', 'r']);
    }

    public function create(User $user): bool
    {
        return $user->can('clinic.access', ['transaction', 'w']);
    }

    public function update(User $user): bool
    {
        return $user->can('clinic.access', ['transaction', 'w']);
    }

    public function delete(User $user): bool
    {
        return $user->can('clinic.access', ['transaction', 'w']);
    }
}
