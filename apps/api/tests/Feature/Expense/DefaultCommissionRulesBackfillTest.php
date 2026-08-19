<?php

namespace Tests\Feature\Expense;

use App\Enums\ClinicRole;
use App\Enums\CommissionRuleType;
use App\Models\CommissionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Aturan fee bawaan hanya lahir lewat pendaftaran klinik baru dan lewat
 * seeder. Klinik yang sudah berjalan sebelum modul fee ada tidak pernah
 * menerimanya — dan diamnya berbahaya: fee Rp5.000 per pasien tidak pernah
 * terhitung, halaman Pengeluaran hanya menampilkan nol.
 */
class DefaultCommissionRulesBackfillTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function runBackfill(): void
    {
        (require database_path(
            'migrations/2026_08_19_001000_seed_default_commission_rules_for_existing_clinics.php'
        ))->up();
    }

    public function test_backfill_installs_the_default_rules_for_an_existing_clinic(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        // Klinik lama: perannya ada, aturan feenya tidak pernah sampai.
        CommissionRule::query()->delete();

        $this->runBackfill();

        $rules = CommissionRule::query()->get();

        $this->assertCount(4, $rules);
        $this->assertEqualsWithDelta(
            5000,
            (float) $rules->firstWhere('type', CommissionRuleType::PerPatient)->amount,
            0.01,
        );
        $this->assertEqualsWithDelta(
            5000,
            (float) $rules->firstWhere('type', CommissionRuleType::PerNewPatient)->amount,
            0.01,
        );

        $tiers = $rules->where('type', CommissionRuleType::RevenuePercent)
            ->sortBy('min_revenue')
            ->values();

        $this->assertEqualsWithDelta(5, (float) $tiers[0]->percent, 0.01);
        $this->assertEqualsWithDelta(6, (float) $tiers[1]->percent, 0.01);
        $this->assertEqualsWithDelta(10_000_000, (float) $tiers[1]->min_revenue, 0.01);
    }

    public function test_backfill_leaves_a_clinic_that_already_tuned_its_rules_alone(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        CommissionRule::query()->delete();
        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee pasien versi klinik',
            'type' => CommissionRuleType::PerPatient,
            'amount' => 7_500,
            'percent' => 0,
            'min_revenue' => 0,
            'is_active' => true,
        ]);

        $this->runBackfill();

        $rules = CommissionRule::query()->get();

        // Tidak ditimpa dan tidak ditambahi: kliniknya sudah mengatur sendiri.
        $this->assertCount(1, $rules);
        $this->assertEqualsWithDelta(7_500, (float) $rules->first()->amount, 0.01);
    }
}
