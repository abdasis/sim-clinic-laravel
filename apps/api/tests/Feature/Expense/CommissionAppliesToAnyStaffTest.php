<?php

namespace Tests\Feature\Expense;

use App\Enums\ClinicRole;
use App\Enums\CommissionRuleType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CommissionRule;
use App\Models\CommissionSetting;
use App\Models\Patient;
use App\Models\Product;
use App\Models\User;
use App\Support\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Komisi penjualan mengikuti siapa yang tercatat menawarkan, sejauh perannya
 * termasuk yang berhak menurut setelan klinik.
 *
 * Yang menjual produk dan layanan bukan hanya terapis — dokter menawarkan
 * treatment lanjutan, kasir menawarkan skincare di meja bayar. Daftar peran
 * yang berhak karena itu tidak boleh tertanam di kode; yang diuji di sini
 * adalah bahwa peran mana pun bisa diikutkan lewat setelan.
 */
class CommissionAppliesToAnyStaffTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /** Ikutkan seluruh peran, seperti yang bisa disetel klinik lewat menu. */
    private function allRolesEligible(): void
    {
        CommissionSetting::query()->delete();
        CommissionSetting::create([
            'tenant_id' => $this->tenant->id,
            'eligible_roles' => ['admin', 'doctor', 'therapist', 'cashier'],
        ]);
    }

    private function percentRule(): void
    {
        CommissionRule::query()->delete();

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Komisi penjualan',
            'type' => CommissionRuleType::RevenuePercent,
            'amount' => 0,
            'percent' => 5,
            'min_revenue' => 0,
            'is_active' => true,
        ]);
    }

    private function staff(string $name, ClinicRole $role): User
    {
        $user = User::create([
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

    public function test_every_role_earns_commission_on_what_it_sold(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->percentRule();
        $this->allRolesEligible();

        $sellers = [
            'dokter' => $this->staff('Dokter Sari', ClinicRole::Doctor),
            'terapis' => $this->staff('Terapis Ratna', ClinicRole::Therapist),
            'kasir' => $this->staff('Kasir Dewi', ClinicRole::Cashier),
        ];

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => 1_000_000,
            'stock_balance' => 100,
        ]);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsClinicUser(ClinicRole::Cashier);

        foreach ($sellers as $seller) {
            $this->postJson($this->tenantUrl('transactions'), [
                'patient_id' => $patient->id,
                'items' => [
                    ['product_id' => $product->id, 'qty' => 1, 'offered_by' => $seller->id],
                ],
            ])->assertCreated();
        }

        foreach ($sellers as $label => $seller) {
            $this->assertEqualsWithDelta(
                50_000,
                $this->rowFor($seller->id)['total'] ?? 0,
                0.01,
                "komisi {$label} tidak terhitung",
            );
        }
    }

    public function test_a_personal_rule_may_be_set_for_any_staff_member(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->percentRule();
        $this->allRolesEligible();

        $kasir = $this->staff('Kasir Dewi', ClinicRole::Cashier);

        // Aturan pribadi untuk kasir harus bisa disimpan — bukan hanya untuk
        // terapis dan dokter.
        $this->postJson($this->tenantUrl('commission-rules'), [
            'name' => 'Komisi kasir',
            'type' => CommissionRuleType::RevenuePercent->value,
            'therapist_id' => $kasir->id,
            'percent' => 8,
            'min_revenue' => 0,
            'is_active' => true,
        ])->assertCreated();

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => 1_000_000,
            'stock_balance' => 10,
        ]);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['product_id' => $product->id, 'qty' => 1, 'offered_by' => $kasir->id]],
        ])->assertCreated();

        // Aturan pribadi menggantikan aturan umum, bukan ditambahkan padanya.
        $this->assertEqualsWithDelta(80_000, $this->rowFor($kasir->id)['total'], 0.01);
    }

    public function test_the_staff_list_for_the_scope_picker_includes_every_role(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->staff('Dokter Sari', ClinicRole::Doctor);
        $this->staff('Terapis Ratna', ClinicRole::Therapist);
        $this->staff('Kasir Dewi', ClinicRole::Cashier);

        $roles = collect($this->getJson($this->tenantUrl('staff?per_page=100'))->assertOk()->json('data'))
            ->pluck('clinic_role')
            ->unique();

        // Pemilih "Berlaku Untuk" membaca daftar ini apa adanya.
        $this->assertContains('doctor', $roles);
        $this->assertContains('therapist', $roles);
        $this->assertContains('cashier', $roles);
    }
}
