<?php

namespace Tests\Feature\Expense;

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
 * Komisi penjualan bertingkat: 5% sampai ambang, 6% setelah terlampaui.
 *
 * Ambangnya disimpan sebagai data, bukan ditulis di kode — admin mengaturnya
 * sendiri lewat menu Pengeluaran. Yang dijaga di sini: tingkatnya tidak pernah
 * dijumlahkan, dan ambang tertinggi yang terlampaui yang dipakai.
 */
class TieredSalesCommissionTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function tiers(): void
    {
        CommissionRule::query()->delete();

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Target penjualan',
            'type' => CommissionRuleType::RevenuePercent,
            'amount' => 0,
            'percent' => 5,
            'min_revenue' => 0,
            'is_active' => true,
        ]);

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Penjualan 10 juta ke atas',
            'type' => CommissionRuleType::RevenuePercent,
            'amount' => 0,
            'percent' => 6,
            'min_revenue' => 10_000_000,
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

    public function test_below_the_threshold_pays_five_percent(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->tiers();

        $seller = $this->seller('Terapis Bawah Ambang');
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->sell($seller, 9_000_000);

        $row = $this->rowFor($seller->id);

        $this->assertEqualsWithDelta(9_000_000, $row['revenue'], 0.01);
        $this->assertEqualsWithDelta(450_000, $row['total'], 0.01);
        $this->assertSame('Target penjualan', $row['lines'][0]['rule_name']);
    }

    public function test_reaching_the_threshold_pays_six_percent_on_everything(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->tiers();

        $seller = $this->seller('Terapis Tembus Ambang');
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->sell($seller, 6_000_000);
        $this->sell($seller, 6_000_000);

        $row = $this->rowFor($seller->id);

        $this->assertEqualsWithDelta(12_000_000, $row['revenue'], 0.01);

        // 6% dari seluruh penjualan, bukan hanya dari kelebihannya, dan bukan
        // 5% + 6% yang dijumlahkan.
        $this->assertEqualsWithDelta(720_000, $row['total'], 0.01);
        $this->assertCount(1, $row['lines']);
        $this->assertSame('Penjualan 10 juta ke atas', $row['lines'][0]['rule_name']);
    }

    public function test_exactly_at_the_threshold_already_counts_as_reached(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->tiers();

        $seller = $this->seller('Terapis Pas Ambang');
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->sell($seller, 10_000_000);

        // "10 juta ke atas" mencakup tepat 10 juta.
        $this->assertEqualsWithDelta(600_000, $this->rowFor($seller->id)['total'], 0.01);
    }

    public function test_the_threshold_is_data_the_clinic_can_change(): void
    {
        $admin = $this->actingAsClinicUser(ClinicRole::Admin);
        $this->tiers();

        // Klinik menurunkan ambangnya lewat menu, tanpa menyentuh kode.
        $this->putJson(
            $this->tenantUrl('commission-rules/'.CommissionRule::where('percent', 6)->first()->id),
            [
                'name' => 'Penjualan 5 juta ke atas',
                'type' => CommissionRuleType::RevenuePercent->value,
                'percent' => 6,
                'min_revenue' => 5_000_000,
                'is_active' => true,
            ],
        )->assertOk();

        $seller = $this->seller('Terapis Ambang Baru');
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->sell($seller, 6_000_000);

        // Sekarang 6 juta sudah cukup untuk 6%.
        $this->assertEqualsWithDelta(360_000, $this->rowFor($seller->id)['total'], 0.01);
        $this->assertNotNull($admin->id);
    }
}
