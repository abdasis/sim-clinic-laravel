<?php

namespace Tests\Feature\Expense;

use App\Enums\ClinicRole;
use App\Enums\CommissionRuleType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CommissionRule;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Support\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Komisi penjualan dihitung dari yang ditawarkan orang itu sendiri.
 *
 * Bukan dari omzet klinik: kalau dasarnya omzet, satu nota yang dijual dokter
 * akan ikut menaikkan komisi setiap terapis, dan klinik membayar berkali-kali
 * untuk penjualan yang sama.
 */
class SalesCommissionAttributionTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function percentRule(float $percent = 5, float $minRevenue = 0): void
    {
        CommissionRule::query()->delete();

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Komisi penjualan',
            'type' => CommissionRuleType::RevenuePercent,
            'amount' => 0,
            'percent' => $percent,
            'min_revenue' => $minRevenue,
            'is_active' => true,
        ]);
    }

    /** Staf yang cuma perlu ada untuk ditunjuk sebagai penawar/pengerja. */
    private function staff(string $name, ClinicRole $role = ClinicRole::Therapist): User
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'email' => str($name)->slug().'@klinik.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => $role,
        ]);

        $user->syncRoles([$role->value]);

        return $user;
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

    public function test_each_person_earns_only_on_what_they_offered(): void
    {
        $admin = $this->actingAsClinicUser(ClinicRole::Admin);
        $this->percentRule(5);

        $terapisA = $this->staff('Terapis A');
        $terapisB = $this->staff('Terapis B');

        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => 1_000_000,
        ]);
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => 200_000,
            'stock_balance' => 10,
        ]);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsClinicUser(ClinicRole::Cashier);

        // Satu nota, dua penawar berbeda.
        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'performer_ids' => [$terapisA->id],
            'items' => [
                ['service_id' => $service->id, 'qty' => 1, 'offered_by' => $terapisA->id],
                ['product_id' => $product->id, 'qty' => 1, 'offered_by' => $terapisB->id],
            ],
        ])->assertCreated();

        $rowA = $this->rowFor($terapisA->id);
        $rowB = $this->rowFor($terapisB->id);

        // A hanya atas layanannya, B hanya atas produknya.
        $this->assertEqualsWithDelta(1_000_000, $rowA['revenue'], 0.01);
        $this->assertEqualsWithDelta(200_000, $rowB['revenue'], 0.01);
        $this->assertEqualsWithDelta(50_000, $rowA['total'], 0.01);
        $this->assertEqualsWithDelta(10_000, $rowB['total'], 0.01);

        // Nilai nota Rp1.200.000 tidak pernah dipakai utuh oleh siapa pun.
        $this->assertLessThan(1_200_000, $rowA['revenue']);
        $this->assertSame([], $this->rowFor($admin->id));
    }

    public function test_a_sale_by_the_doctor_does_not_pay_the_therapist_too(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->percentRule(5);

        $dokter = $this->staff('Dokter Sari', ClinicRole::Doctor);
        $terapis = $this->staff('Terapis Ratna');

        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => 3_000_000,
        ]);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'performer_ids' => [$dokter->id],
            'items' => [
                ['service_id' => $service->id, 'qty' => 1, 'offered_by' => $dokter->id],
            ],
        ])->assertCreated();

        $this->assertEqualsWithDelta(150_000, $this->rowFor($dokter->id)['total'], 0.01);

        // Terapis yang tidak menawarkan apa pun tidak mendapat komisi dari
        // penjualan orang lain — inilah yang mencegah pembayaran ganda.
        $this->assertSame([], $this->rowFor($terapis->id));
    }

    public function test_unattributed_sale_pays_no_one(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->percentRule(5);

        $terapis = $this->staff('Terapis Tanpa Penjualan');

        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => 500_000,
        ]);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsClinicUser(ClinicRole::Cashier);

        // Kasir lupa mencatat penawarnya: komisi penjualan tidak jatuh ke
        // siapa pun, bukan dibagi rata ke semua orang.
        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'performer_ids' => [$terapis->id],
            'items' => [['service_id' => $service->id, 'qty' => 1]],
        ])->assertCreated();

        $row = $this->rowFor($terapis->id);

        $this->assertEqualsWithDelta(0, $row['revenue'], 0.01);
        $this->assertSame(
            [],
            collect($row['lines'])->where('type', CommissionRuleType::RevenuePercent)->values()->all(),
        );
    }
}
