<?php

namespace App\Policies;

use App\Models\User;

/**
 * Satuan hanya urusan admin: yang membuka formulir produk cuma admin, dan
 * layanan tidak memakai satuan sama sekali. Berbeda dari kategori, yang
 * dibaca dokter dan terapis karena formulir layanan memerlukannya.
 */
class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('unit.view');
    }

    public function view(User $user): bool
    {
        return $user->can('unit.view');
    }

    public function create(User $user): bool
    {
        return $user->can('unit.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('unit.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('unit.manage');
    }
}
