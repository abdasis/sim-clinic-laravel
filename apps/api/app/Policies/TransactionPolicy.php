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
        return $user->can('transaction.view');
    }

    public function view(User $user): bool
    {
        return $user->can('transaction.view');
    }

    public function create(User $user): bool
    {
        return $user->can('transaction.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('transaction.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('transaction.manage');
    }
}
