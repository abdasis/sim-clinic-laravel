<?php

namespace App\Actions\User;

use App\Actions\LogAuditAction;
use App\Models\User;

/**
 * Simpan preferensi tampilan seorang pengguna.
 *
 * Nilai lama ikut dicatat supaya audit log bisa menjawab "sejak kapan
 * tampilannya berubah", bukan sekadar mencatat bahwa ada perubahan.
 */
class UpdateUserAppearanceAction
{
    /**
     * @param  array<string, string>  $appearance
     */
    public function handle(User $user, array $appearance): User
    {
        $previous = $user->appearance;

        $user->update(['appearance' => $appearance]);

        app(LogAuditAction::class)->handle(
            'user.appearance_updated',
            $user,
            auth()->user(),
            ['old' => $previous, 'new' => $appearance],
            'Memperbarui preferensi tampilan pengguna '.$user->name.'.',
            $user->tenant,
        );

        return $user->refresh();
    }
}
