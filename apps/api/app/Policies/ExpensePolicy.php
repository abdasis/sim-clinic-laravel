<?php

namespace App\Policies;

use App\Models\User;

/**
 * Pengeluaran memuat gaji dan insentif, jadi hanya admin yang boleh
 * melihat apalagi mengubahnya (R2).
 */
class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('expense.view');
    }

    public function view(User $user): bool
    {
        return $user->can('expense.view');
    }

    public function create(User $user): bool
    {
        return $user->can('expense.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('expense.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('expense.manage');
    }
}
