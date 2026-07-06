<?php

namespace App\Policies;

use App\Models\User;

/**
 * Invoice: print/lihat untuk yang punya akses baca modul invoice (Admin/Kasir).
 */
class InvoicePolicy
{
    public function view(User $user): bool
    {
        return $user->can('clinic.access', ['invoice', 'r']);
    }
}
