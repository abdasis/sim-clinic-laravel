<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu penyedia WhatsApp saja: WAHA.
 *
 * Sebelumnya tiap klinik menyimpan sendiri alamat dan kredensial gateway-nya.
 * Server WAHA-nya satu dan dikelola pengelola platform, jadi alamat dan API
 * key-nya pindah ke tabel central; yang tersisa di klinik hanya nama sesinya.
 *
 * Nama sesi diisikan dari slug klinik untuk baris yang sudah ada. Tanpa itu,
 * klinik yang tadinya terhubung akan berhenti mengirim tanpa ada yang
 * mengubah pengaturannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waha_settings', function (Blueprint $table) {
            $table->id();
            $table->string('base_url')->nullable();
            // Tersimpan terenkripsi, jadi jauh lebih panjang dari kunci aslinya.
            $table->text('api_key')->nullable();
            $table->timestamps();
        });

        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->string('session')->nullable()->after('tenant_id');
        });

        $this->seedSessionsFromTenantSlug();

        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->dropColumn(['driver', 'api_url', 'api_token']);
        });

        Schema::table('whatsapp_settings', function (Blueprint $table) {
            // Satu sesi WAHA hanya boleh dipakai satu klinik; dua klinik yang
            // menunjuk sesi sama akan saling mengirim dari nomor yang sama.
            $table->unique('session');
        });
    }

    /**
     * Slug klinik dipakai apa adanya sebagai nama sesi: sudah unik per
     * klinik, dan formatnya (huruf kecil, angka, strip) memang yang diterima
     * WAHA.
     */
    private function seedSessionsFromTenantSlug(): void
    {
        DB::table('whatsapp_settings')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $slug = DB::table('tenants')->where('id', $row->tenant_id)->value('slug');

                    if ($slug === null) {
                        continue;
                    }

                    DB::table('whatsapp_settings')
                        ->where('id', $row->id)
                        ->update(['session' => $slug]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->dropUnique(['session']);
            $table->dropColumn('session');
        });

        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->string('driver')->default('manual');
            $table->string('api_url')->nullable();
            $table->string('api_token')->nullable();
        });

        Schema::dropIfExists('waha_settings');
    }
};
