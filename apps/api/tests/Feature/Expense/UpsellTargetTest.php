<?php

namespace Tests\Feature\Expense;

use App\Actions\Tenant\SeedDefaultCommissionRulesAction;
use App\Enums\ClinicRole;
use App\Enums\CommissionRuleType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CommissionRule;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Support\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Komisi upselling: tercapai 3 juta sebulan, dapat 5%.
 *
 * Dua hal yang dijaga di sini. Pertama ambangnya: aturan bawaan lahir dengan
 * min_revenue 0, jadi 5% jalan sejak rupiah pertama — itu yang membuat satu
 * baris laporan berbunyi Rp11.400 padahal fee per pasien jelas Rp5.000.
 * Kedua keterbacaannya: komisi upselling harus bisa dibedakan dari fee kerja
 * dan punya keterangan targetnya, karena yang ditanyakan terapis bukan
 * "berapa totalnya" melainkan "omzet saya sudah berapa, targetnya kena belum".
 */
class UpsellTargetTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const TARGET = 3_000_000;

    /** Aturan seperti yang dipakai klinik: fee kerja 5rb + komisi 5% di 3 juta. */
    private function rules(): void
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
            'name' => 'Target penjualan',
            'type' => CommissionRuleType::RevenuePercent,
            'amount' => 0,
            'percent' => 5,
            'min_revenue' => self::TARGET,
            'is_active' => true,
        ]);
    }

    private function seller(string $name): User
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'email' => str($name)->slug().'@klinik.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Therapist,
        ]);

        $user->syncRoles([ClinicRole::Therapist->value]);

        return $user;
    }

    private function sell(User $seller, int $amount): void
    {
        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => $amount,
        ]);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'performer_ids' => [$seller->id],
            'items' => [
                ['service_id' => $service->id, 'qty' => 1, 'offered_by' => $seller->id],
            ],
        ])->assertCreated();
    }

    /** @return array<string, mixed> */
    private function rowFor(int $userId): array
    {
        $result = (new CommissionCalculator(
            now()->toDateString(),
            now()->toDateString(),
        ))->run();

        return collect($result['rows'])->firstWhere('therapist_id', $userId) ?? [];
    }

    public function test_below_the_target_pays_the_work_fee_only(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->rules();

        $seller = $this->seller('Terapis Belum Target');
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->sell($seller, 2_000_000);

        $row = $this->rowFor($seller->id);

        $this->assertEqualsWithDelta(2_000_000, $row['revenue'], 0.01);
        $this->assertEqualsWithDelta(0, $row['commission_amount'], 0.01);
        $this->assertEqualsWithDelta(5_000, $row['fee_amount'], 0.01);
        $this->assertEqualsWithDelta(5_000, $row['total'], 0.01);
    }

    public function test_reaching_the_target_pays_five_percent_of_everything(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->rules();

        $seller = $this->seller('Terapis Tembus Target');
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->sell($seller, 2_000_000);
        $this->sell($seller, 1_000_000);

        $row = $this->rowFor($seller->id);

        // 5% dari seluruh 3 juta, bukan hanya dari nota yang melewatinya.
        $this->assertEqualsWithDelta(3_000_000, $row['revenue'], 0.01);
        $this->assertEqualsWithDelta(150_000, $row['commission_amount'], 0.01);

        // Fee kerja tetap terpisah: dua kunjungan, 5rb masing-masing.
        $this->assertEqualsWithDelta(10_000, $row['fee_amount'], 0.01);
        $this->assertEqualsWithDelta(160_000, $row['total'], 0.01);
    }

    /**
     * Keterangan target ikut dibawa walau belum tercapai — tanpa itu laporan
     * hanya bisa bilang "komisi Rp0" tanpa menyebut kurang berapa lagi.
     */
    public function test_the_row_states_the_target_even_when_unmet(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->rules();

        $seller = $this->seller('Terapis Kurang Sedikit');
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->sell($seller, 2_900_000);

        $row = $this->rowFor($seller->id);

        $this->assertEqualsWithDelta(self::TARGET, $row['upsell_target'], 0.01);
        $this->assertEqualsWithDelta(5, $row['upsell_percent'], 0.01);
        $this->assertFalse($row['upsell_reached']);
    }

    public function test_the_row_marks_the_target_as_reached(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->rules();

        $seller = $this->seller('Terapis Pas Target');
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->sell($seller, self::TARGET);

        $row = $this->rowFor($seller->id);

        $this->assertTrue($row['upsell_reached']);
        $this->assertEqualsWithDelta(self::TARGET, $row['upsell_target'], 0.01);
    }

    /**
     * Dengan dua tingkat, yang ditampilkan tingkat tertinggi yang tercapai —
     * itu yang sedang ia terima, bukan yang sudah ia lewati.
     */
    public function test_the_shown_tier_is_the_highest_one_reached(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->rules();

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Penjualan 10 juta ke atas',
            'type' => CommissionRuleType::RevenuePercent,
            'amount' => 0,
            'percent' => 6,
            'min_revenue' => 10_000_000,
            'is_active' => true,
        ]);

        $seller = $this->seller('Terapis Tingkat Dua');
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->sell($seller, 11_000_000);

        $row = $this->rowFor($seller->id);

        $this->assertEqualsWithDelta(10_000_000, $row['upsell_target'], 0.01);
        $this->assertEqualsWithDelta(6, $row['upsell_percent'], 0.01);
        $this->assertTrue($row['upsell_reached']);
    }

    public function test_the_default_rule_starts_at_three_million(): void
    {
        $target = collect(SeedDefaultCommissionRulesAction::defaults())
            ->firstWhere('name', 'Target penjualan');

        $this->assertSame(self::TARGET, $target['min_revenue']);
        $this->assertSame(5, $target['percent']);
    }
}
