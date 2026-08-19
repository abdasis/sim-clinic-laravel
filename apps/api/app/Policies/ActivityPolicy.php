<?php

namespace App\Policies;

use App\Models\User;

/**
 * Log aktivitas memuat nilai lama-baru seluruh modul, termasuk gaji dan
 * data pasien, jadi hanya peran yang diberi izin `activity_log.view` yang
 * boleh membukanya. Tidak ada izin tulis: log dibuat sistem, tidak pernah
 * diubah dari layar.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('activity_log.view');
    }

    public function view(User $user): bool
    {
        return $user->can('activity_log.view');
    }
}
