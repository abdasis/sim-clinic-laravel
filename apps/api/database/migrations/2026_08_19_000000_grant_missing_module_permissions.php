<?php

use App\Actions\Tenant\GrantMissingModulePermissionsAction;
use Illuminate\Database\Migrations\Migration;

/**
 * Lengkapi izin peran klinik yang tertinggal.
 *
 * Klinik yang sudah berjalan tidak pernah menerima izin modul yang
 * ditambahkan sesudah kliniknya dibuat: deploy hanya menjalankan migrasi,
 * sementara yang membuat izinnya adalah seeder. Akibatnya admin ditolak di
 * halaman yang menurut sidebar boleh dibuka.
 *
 * Migrasi ini membereskan keadaan sekarang; supaya tidak terulang, perintah
 * yang sama ikut dijalankan tiap deploy lewat deploy.sh.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(GrantMissingModulePermissionsAction::class)->forAllTenants();
    }

    public function down(): void
    {
        // Hanya menambah izin yang memang seharusnya ada sejak awal; tidak ada
        // yang perlu dikembalikan, dan mencabutnya justru mematikan modul yang
        // sudah dipakai.
    }
};
