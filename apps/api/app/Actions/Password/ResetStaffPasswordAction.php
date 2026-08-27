<?php

namespace App\Actions\Password;

use App\Actions\LogAuditAction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Admin menyetel ulang kata sandi seorang staf.
 *
 * Diperlukan karena klinik tidak punya jalur lupa-kata-sandi lewat email:
 * tanpa ini, terapis yang lupa kata sandinya terkunci selamanya dan satu-
 * satunya jalan adalah membuat akun baru — yang memutus riwayat kerjanya.
 *
 * Seluruh sesi staf itu dicabut tanpa kecuali. Penyetelan ulang oleh admin
 * berarti akunnya sedang dipulihkan atau diamankan, dan membiarkan sesi lama
 * tetap hidup meniadakan gunanya.
 */
class ResetStaffPasswordAction
{
    public function handle(User $staff, string $newPassword, ?User $causer = null): void
    {
        try {
            $staff->forceFill(['password' => Hash::make($newPassword)])->save();

            $staff->tokens()->delete();
        } catch (Throwable $e) {
            Log::error('Gagal menyetel ulang kata sandi staf.', [
                'exception' => $e,
                'staff_id' => $staff->id,
                'tenant_id' => $staff->tenant_id,
            ]);

            throw $e;
        }

        app(LogAuditAction::class)->handle(
            action: 'staff.password_reset',
            subject: $staff,
            causer: $causer,
            // Kata sandinya tidak pernah ikut dicatat; yang perlu tercatat
            // adalah siapa menyetel ulang milik siapa.
            context: ['staff_id' => $staff->id, 'staff_name' => $staff->name],
            description: 'Menyetel ulang kata sandi '.$staff->name.'.',
        );
    }
}
