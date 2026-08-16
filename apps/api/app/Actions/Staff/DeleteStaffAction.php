<?php

namespace App\Actions\Staff;

use App\Actions\LogAuditAction;
use App\Actions\Staff\Concerns\GuardsLastAdmin;
use App\Models\User;
use App\Support\StaffReferences;
use Illuminate\Support\Facades\DB;

/**
 * Hapus akun staf secara permanen.
 *
 * Hanya boleh untuk staf yang belum meninggalkan jejak apa pun. Sebagian
 * foreign key ke tabel users memakai cascade, jadi menghapus staf yang pernah
 * bekerja akan ikut menghapus booking dan rekam medis yang ia buat — riwayat
 * klinik dan catatan medis pasien hilang tanpa peringatan. Staf yang sudah
 * bekerja dinonaktifkan, bukan dihapus.
 */
class DeleteStaffAction
{
    use GuardsLastAdmin;

    public function handle(User $staff): void
    {
        $this->guardLastAdmin($staff);

        if (app(StaffReferences::class)->has($staff->id)) {
            abort(422, __('staff.has_history'));
        }

        $attributes = [
            'name' => $staff->name,
            'email' => $staff->email,
            'clinic_role' => $staff->clinic_role?->value,
        ];

        DB::transaction(function () use ($staff): void {
            $staff->syncRoles([]);
            $staff->forceDelete();
        });

        app(LogAuditAction::class)->handle(
            'staff.deleted',
            $staff,
            auth()->user(),
            ['attributes' => $attributes],
            'Menghapus akun staf '.$attributes['name'].' — peran '
                .($staff->clinic_role?->label() ?? '-').'. Akun ini belum pernah mencatat data apa pun.',
        );
    }
}
