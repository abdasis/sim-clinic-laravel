<?php

use App\Enums\ClinicRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Peran mana yang berhak menerima fee dan komisi.
 *
 * Bukan daftar tetap di kode: klinik memutuskan sendiri. Sekarang kasir belum
 * ikut, tapi ia menawarkan skincare di meja bayar dan suatu saat bisa
 * diikutkan — dan saat itu tiba, yang berubah cukup setelan, bukan aplikasi.
 */
return new class extends Migration
{
    private const DEFAULT_ROLES = [ClinicRole::Doctor->value, ClinicRole::Therapist->value];

    public function up(): void
    {
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->json('eligible_roles');
            $table->timestamps();
        });

        // Klinik yang sudah berjalan memulai dengan keadaan yang berlaku
        // sekarang: dokter dan terapis.
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('commission_settings')->insert([
                'tenant_id' => $tenantId,
                'eligible_roles' => json_encode(self::DEFAULT_ROLES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settings');
    }
};
