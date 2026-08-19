<?php

use App\Actions\Tenant\SeedDefaultCommissionRulesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pasang aturan fee bawaan untuk klinik yang sudah ada.
 *
 * Sama seperti izin modul, aturan fee bawaan hanya lahir lewat pendaftaran
 * klinik baru dan lewat seeder — sementara deploy cuma menjalankan migrate.
 * Klinik yang sudah berjalan sebelum modul fee ada tidak pernah menerimanya,
 * dan akibatnya diam-diam serius: fee terapis Rp5.000 per pasien serta komisi
 * omzet 5%/6% tidak pernah terhitung, dan halaman Pengeluaran hanya
 * menampilkan nol tanpa menjelaskan sebabnya.
 *
 * Aman diulang: Action-nya melewati klinik yang sudah punya aturan sendiri,
 * jadi penyesuaian yang sudah dibuat admin tidak tersentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        $action = app(SeedDefaultCommissionRulesAction::class);

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $action->handle((int) $tenantId);
        }
    }

    public function down(): void
    {
        // Tidak dibatalkan: aturan yang sudah dipakai menghitung fee tidak
        // boleh hilang, dan yang bawaan tidak bisa dibedakan dari yang sudah
        // disesuaikan admin.
    }
};
