<?php

use App\Actions\Tenant\GrantMissingModulePermissionsAction;
use Illuminate\Database\Migrations\Migration;

/**
 * Izin modul satuan untuk klinik yang sudah berjalan.
 *
 * Izin peran dipasang sekali saat klinik dibuat, jadi modul yang lahir
 * belakangan tidak pernah sampai ke sana. Tanpa migrasi ini, menu Satuan
 * muncul di sidebar admin tapi halamannya menolak dibuka.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(GrantMissingModulePermissionsAction::class)->forAllTenants();
    }

    public function down(): void
    {
        // Hanya menambah izin yang memang seharusnya ada; mencabutnya
        // mematikan modul yang sudah dipakai.
    }
};
