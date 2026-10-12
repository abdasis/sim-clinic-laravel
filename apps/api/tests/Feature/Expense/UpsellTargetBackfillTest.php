<?php

namespace Tests\Feature\Expense;

use App\Enums\ClinicRole;
use App\Enums\CommissionRuleType;
use App\Models\CommissionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Klinik yang sudah berjalan dengan ambang bawaan 0.
 *
 * Menaikkannya tidak boleh menimpa tarif lama: gaji dibayar bulanan, dan
 * bulan yang sudah ditutup harus tetap dihitung dengan tarif yang dipakai
 * saat dibayarkan. Yang berubah hanya berlaku sejak awal bulan berjalan.
 */
class UpsellTargetBackfillTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function runBackfill(): void
    {
        (require database_path(
            'migrations/2026_10_12_090000_set_upsell_target_to_three_million.php'
        ))->up();
    }

    private function target(int $minRevenue, string $name = 'Target penjualan'): CommissionRule
    {
        return CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'type' => CommissionRuleType::RevenuePercent,
            'amount' => 0,
            'percent' => 5,
            'min_revenue' => $minRevenue,
            'is_active' => true,
        ]);
    }

    public function test_raises_the_default_target_to_three_million(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        CommissionRule::query()->delete();
        $this->target(0);

        $this->runBackfill();

        $current = CommissionRule::whereNull('effective_to')->get();

        $this->assertCount(1, $current);
        $this->assertEqualsWithDelta(3_000_000, (float) $current->first()->min_revenue, 0.01);
        $this->assertEqualsWithDelta(5, (float) $current->first()->percent, 0.01);
    }

    /** Tarif lama tetap ada, hanya ditutup — bulan lalu masih bisa dihitung. */
    public function test_keeps_the_old_rate_as_a_closed_version(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        CommissionRule::query()->delete();
        $old = $this->target(0);

        $this->runBackfill();

        $old->refresh();

        $this->assertNotNull($old->effective_to);
        $this->assertEqualsWithDelta(0, (float) $old->min_revenue, 0.01);

        // Batasnya awal bulan, bukan tengah bulan: bulan berjalan tidak
        // terbelah jadi dua tarif.
        $this->assertSame(
            now()->startOfMonth()->toDateTimeString(),
            $old->effective_to->toDateTimeString(),
        );
    }

    /** Klinik yang sudah menyetel ambangnya sendiri tidak disentuh. */
    public function test_leaves_a_clinic_configured_target_alone(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        CommissionRule::query()->delete();
        $configured = $this->target(5_000_000);

        $this->runBackfill();

        $configured->refresh();

        $this->assertNull($configured->effective_to);
        $this->assertEqualsWithDelta(5_000_000, (float) $configured->min_revenue, 0.01);
        $this->assertCount(1, CommissionRule::all());
    }

    /** Aturan bernama lain bukan urusan migrasi ini, berapa pun ambangnya. */
    public function test_leaves_other_rules_alone(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        CommissionRule::query()->delete();
        $other = $this->target(0, 'Komisi khusus paket');

        $this->runBackfill();

        $other->refresh();

        $this->assertNull($other->effective_to);
        $this->assertEqualsWithDelta(0, (float) $other->min_revenue, 0.01);
    }

    /** Dijalankan dua kali tidak melahirkan versi kedua. */
    public function test_running_twice_changes_nothing_more(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        CommissionRule::query()->delete();
        $this->target(0);

        $this->runBackfill();
        $this->runBackfill();

        $this->assertCount(2, CommissionRule::all());
        $this->assertCount(1, CommissionRule::whereNull('effective_to')->get());
    }
}
