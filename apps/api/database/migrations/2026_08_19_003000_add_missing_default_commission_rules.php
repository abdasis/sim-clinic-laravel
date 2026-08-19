<?php

use App\Actions\Tenant\SeedDefaultCommissionRulesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lengkapi aturan fee bawaan yang belum ada, satu per satu.
 *
 * Migrasi sebelumnya memakai SeedDefaultCommissionRulesAction, yang melewati
 * klinik yang sudah punya aturan apa pun. Klinik yang sudah memiliki fee per
 * pasien dan bonus pasien baru — tapi belum pernah menerima kedua tingkat
 * komisi penjualan 5% dan 6% — karena itu tidak mendapat apa-apa, dan
 * pengaturan tingkatnya tidak pernah muncul di menu.
 *
 * Di sini pencocokannya per nama aturan, bukan "punya aturan atau tidak".
 * Aturan yang sudah ada tidak disentuh sama sekali: nominal, persentase,
 * ambang, dan status aktifnya tetap seperti yang disetel admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $existing = DB::table('commission_rules')
                ->where('tenant_id', $tenantId)
                ->pluck('name')
                ->all();

            foreach (SeedDefaultCommissionRulesAction::defaults() as $rule) {
                if (in_array($rule['name'], $existing, true)) {
                    continue;
                }

                DB::table('commission_rules')->insert([
                    'tenant_id' => $tenantId,
                    'name' => $rule['name'],
                    'type' => $rule['type']->value,
                    'amount' => $rule['amount'],
                    'percent' => $rule['percent'],
                    'min_revenue' => $rule['min_revenue'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Tidak dibatalkan: aturan yang sudah dipakai menghitung fee tidak
        // boleh hilang, dan yang ditambahkan di sini tidak bisa dibedakan
        // dari yang sudah lebih dulu ada.
    }
};
