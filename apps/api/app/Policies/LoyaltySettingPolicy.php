<?php

namespace App\Policies;

use App\Models\User;

/**
 * Tarif poin: Admin menyetel, Kasir cukup melihat.
 *
 * Kasir perlu membacanya bukan untuk mengubah, melainkan karena layar bayar
 * menampilkan perkiraan poin dan nilai penukarannya sebelum nota terbit.
 */
class LoyaltySettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('loyalty.view');
    }

    public function view(User $user): bool
    {
        return $user->can('loyalty.view');
    }

    public function update(User $user): bool
    {
        return $user->can('loyalty.manage');
    }
}
