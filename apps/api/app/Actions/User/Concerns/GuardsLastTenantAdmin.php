<?php

namespace App\Actions\User\Concerns;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * Tenant harus selalu punya minimal satu admin aktif — baik lewat penurunan
 * peran maupun lewat pencabutan keanggotaan.
 *
 * Aturannya tinggal di satu tempat: dua salinan yang dirawat terpisah pasti
 * menyimpang, dan yang menyimpang di sini berarti klinik bisa kehilangan admin
 * terakhirnya lewat jalur yang terlupa diperbarui.
 */
trait GuardsLastTenantAdmin
{
    /**
     * @param  UserRole|null  $newRole  Peran tujuan; null berarti keanggotaannya dicabut.
     */
    protected function guardLastTenantAdmin(User $user, ?UserRole $newRole = null): void
    {
        if ($user->role !== UserRole::TenantAdmin || $newRole === UserRole::TenantAdmin) {
            return;
        }

        $activeAdmins = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('role', UserRole::TenantAdmin)
            ->where('status', UserStatus::Active)
            ->count();

        if ($activeAdmins <= 1) {
            abort(422, __('tenant.last_admin'));
        }
    }
}
