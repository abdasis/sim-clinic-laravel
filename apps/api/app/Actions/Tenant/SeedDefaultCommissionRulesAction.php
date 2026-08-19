<?php

namespace App\Actions\Tenant;

use App\Actions\LogAuditAction;
use App\Enums\CommissionRuleType;
use App\Models\CommissionRule;

/**
 * Pasang aturan fee bawaan untuk tenant baru sesuai praktik klinik:
 * fee per pasien, bonus pasien baru, dan komisi omzet bertingkat.
 *
 * Nominalnya business rule di sini — bukan angka yang diketik frontend —
 * dan tetap bisa disesuaikan tiap tenant lewat menu Pengeluaran.
 */
class SeedDefaultCommissionRulesAction
{
    /**
     * Aturan bawaan klinik. Dibuka supaya migrasi pelengkap bisa memakai
     * daftar yang sama, tanpa menyalinnya dan berisiko menyimpang.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return array_map(fn (array $rule) => $rule + [
            'amount' => $rule['amount'] ?? 0,
            'percent' => $rule['percent'] ?? 0,
            'min_revenue' => $rule['min_revenue'] ?? 0,
            'is_active' => true,
        ], self::DEFAULTS);
    }

    private const DEFAULTS = [
        ['name' => 'Fee pasien', 'type' => CommissionRuleType::PerPatient, 'amount' => 5000],
        ['name' => 'Bonus pasien baru', 'type' => CommissionRuleType::PerNewPatient, 'amount' => 5000],
        ['name' => 'Target penjualan', 'type' => CommissionRuleType::RevenuePercent, 'percent' => 5, 'min_revenue' => 0],
        ['name' => 'Penjualan 10 juta ke atas', 'type' => CommissionRuleType::RevenuePercent, 'percent' => 6, 'min_revenue' => 10_000_000],
    ];

    public function handle(int $tenantId): void
    {
        // Idempoten: tenant yang sudah menyetel aturannya sendiri tidak
        // boleh ditimpa oleh seeding ulang.
        if (CommissionRule::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $created = [];

        foreach (self::defaults() as $rule) {
            $created[] = CommissionRule::withoutGlobalScopes()
                ->create($rule + ['tenant_id' => $tenantId])
                ->getAttributes();
        }

        app(LogAuditAction::class)->handle(
            'commission_rule.defaults_seeded',
            null,
            auth()->user(),
            ['tenant_id' => $tenantId, 'new' => ['rules' => $created]],
            'Memasang '.count($created).' aturan fee bawaan untuk klinik baru.',
        );
    }
}
