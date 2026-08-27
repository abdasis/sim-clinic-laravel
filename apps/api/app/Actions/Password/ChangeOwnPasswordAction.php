<?php

namespace App\Actions\Password;

use App\Actions\LogAuditAction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ganti kata sandi milik sendiri.
 *
 * Sesi lain dicabut, sesi yang sedang dipakai dibiarkan hidup. Alasannya
 * bukan kenyamanan: kalau kata sandinya diganti justru karena ada yang
 * mengetahuinya, orang itu harus ikut terlempar keluar — dan satu-satunya
 * cara memastikannya adalah mencabut token selain milik peramban yang
 * sedang menggantinya.
 */
class ChangeOwnPasswordAction
{
    public function handle(User $user, string $newPassword, ?string $keepTokenId = null): void
    {
        try {
            $user->forceFill(['password' => Hash::make($newPassword)])->save();

            $tokens = $user->tokens();

            if ($keepTokenId !== null) {
                $tokens->where('id', '!=', $keepTokenId);
            }

            $tokens->delete();
        } catch (Throwable $e) {
            Log::error('Gagal mengganti kata sandi sendiri.', [
                'exception' => $e,
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
            ]);

            throw $e;
        }

        app(LogAuditAction::class)->handle(
            action: 'user.password_changed',
            subject: $user,
            causer: $user,
            // Kata sandinya sendiri tidak pernah ikut dicatat, baik yang lama
            // maupun yang baru — log audit dibaca banyak orang.
            context: ['user_id' => $user->id, 'self' => true],
            description: $user->name.' mengganti kata sandinya sendiri.',
        );
    }
}
