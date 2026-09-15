<?php

use App\Actions\Tenant\GrantMissingModulePermissionsAction;
use Illuminate\Database\Migrations\Migration;

/**
 * Izin modul poin loyalitas untuk klinik yang sudah berjalan.
 *
 * Modul `membership` berganti nama jadi `loyalty` saat tingkat member dihapus.
 * Izin peran dipasang sekali waktu kliniknya dibuat, jadi tanpa migrasi ini
 * menu Poin Loyalitas muncul di sidebar admin tapi halamannya menolak dibuka.
 *
 * Izin `membership.*` yang lama sengaja dibiarkan: mencabut izin bukan
 * pekerjaan migrasi yang sedang menambah, dan barisnya tidak menyakiti siapa
 * pun karena tidak ada lagi yang memeriksanya.
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
