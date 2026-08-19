<?php

namespace Tests\Feature\Expense;

use App\Enums\ClinicRole;
use App\Enums\CommissionRuleType;
use App\Models\CommissionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Klinik yang sudah punya sebagian aturan fee.
 *
 * Pelengkap sebelumnya melewati klinik yang sudah punya aturan apa pun, jadi
 * klinik dengan fee per pasien dan bonus pasien baru tidak pernah menerima
 * kedua tingkat komisi penjualan — dan pengaturan 5%/6% tidak pernah muncul
 * di menu.
 */
class MissingCommissionTiersBackfillTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function runBackfill(): void
    {
        (require database_path(
            'migrations/2026_08_19_003000_add_missing_default_commission_rules.php'
        ))->up();
    }

    /** Keadaan klinik di server: dua aturan nominal, tanpa tingkat persentase. */
    private function onlyFlatRules(): void
    {
        CommissionRule::query()->delete();

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee pasien',
            'type' => CommissionRuleType::PerPatient,
            'amount' => 5_000,
            'percent' => 0,
            'min_revenue' => 0,
            'is_active' => true,
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bonus pasien baru',
            'type' => CommissionRuleType::PerNewPatient,
            'amount' => 5_000,
            'percent' => 0,
            'min_revenue' => 0,
            'is_active' => true,
        ]);
    }

    public function test_adds_only_the_two_missing_percentage_tiers(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->onlyFlatRules();

        $this->runBackfill();

        $tiers = CommissionRule::query()
            ->where('type', CommissionRuleType::RevenuePercent)
            ->orderBy('min_revenue')
            ->get();

        $this->assertCount(2, $tiers);
        $this->assertEqualsWithDelta(5, (float) $tiers[0]->percent, 0.01);
        $this->assertEqualsWithDelta(0, (float) $tiers[0]->min_revenue, 0.01);
        $this->assertEqualsWithDelta(6, (float) $tiers[1]->percent, 0.01);
        $this->assertEqualsWithDelta(10_000_000, (float) $tiers[1]->min_revenue, 0.01);

        $this->assertSame(4, CommissionRule::query()->count());
    }

    public function test_leaves_the_rules_that_were_already_there_untouched(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->onlyFlatRules();

        // Klinik menaikkan fee per pasiennya sendiri.
        CommissionRule::query()->where('name', 'Fee pasien')->update(['amount' => 7_500]);

        $this->runBackfill();

        $this->assertEqualsWithDelta(
            7_500,
            (float) CommissionRule::query()->where('name', 'Fee pasien')->first()->amount,
            0.01,
        );
        $this->assertSame(1, CommissionRule::query()->where('name', 'Fee pasien')->count());
    }

    public function test_running_it_twice_adds_nothing_more(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->onlyFlatRules();

        $this->runBackfill();
        $this->runBackfill();

        $this->assertSame(4, CommissionRule::query()->count());
    }

    public function test_the_tiers_show_up_in_the_settings_list(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->onlyFlatRules();
        $this->runBackfill();

        $names = collect($this->getJson($this->tenantUrl('commission-rules'))->assertOk()->json('data'))
            ->pluck('name');

        // Inilah yang dicari admin di menu Pengeluaran.
        $this->assertContains('Target penjualan', $names);
        $this->assertContains('Penjualan 10 juta ke atas', $names);
    }
}
