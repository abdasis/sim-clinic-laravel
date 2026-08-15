<?php

namespace App\Services;

use App\Actions\User\UpdateUserAppearanceAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Profil milik pengguna sendiri. Service hanya mengatur batas transaksi dan
 * memanggil Action; sentuhan ke database seluruhnya ada di Action.
 */
class ProfileService
{
    /**
     * @param  array<string, string>  $appearance
     */
    public function updateAppearance(User $user, array $appearance): User
    {
        return DB::transaction(
            fn () => app(UpdateUserAppearanceAction::class)->handle($user, $appearance),
        );
    }
}
