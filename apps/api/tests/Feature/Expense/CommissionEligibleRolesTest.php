<?php

namespace Tests\Feature\Expense;

use App\Enums\ClinicRole;
use App\Enums\CommissionRuleType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CommissionRule;
use App\Models\Patient;
use App\Models\Product;
use App\Models\User;
use App\Support\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Peran yang berhak menerima fee dan komisi adalah setelan klinik.
 *
 * Sekarang kasir belum ikut, tapi ia menawarkan skincare di meja bayar dan
 * suatu saat bisa diikutkan. Menyimpannya sebagai data berarti hari itu cukup
 * mencentang satu kotak, bukan menunggu rilis.
 */
class CommissionEligibleRolesTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

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

    private function sell(User $seller): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => 1_000_000,
            'stock_balance' => 100,
        ]);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['product_id' => $product->id, 'qty' => 1, 'offered_by' => $seller->id]],
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

    public function test_default_is_doctor_and_therapist_only(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->getJson($this->tenantUrl('commission-rules/setting'))
            ->assertOk()
            ->assertJsonPath('data.eligible_roles', ['doctor', 'therapist']);
    }

    public function test_cashier_earns_nothing_while_not_eligible(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->percentRule();

        $dokter = $this->staff('Dokter Sari', ClinicRole::Doctor);
        $kasir = $this->staff('Kasir Dewi', ClinicRole::Cashier);

        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->sell($dokter);
        $this->sell($kasir);

        $this->assertEqualsWithDelta(50_000, $this->rowFor($dokter->id)['total'], 0.01);
        $this->assertSame([], $this->rowFor($kasir->id));
    }

    public function test_including_the_cashier_takes_effect_without_touching_the_code(): void
    {
        $admin = $this->actingAsClinicUser(ClinicRole::Admin);
        $this->percentRule();

        $kasir = $this->staff('Kasir Dewi', ClinicRole::Cashier);

        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->sell($kasir);
        $this->assertSame([], $this->rowFor($kasir->id));

        // Hari klinik memutuskan kasir ikut: satu setelan, bukan satu rilis.
        $this->actingAs($admin, 'sanctum');
        $this->putJson($this->tenantUrl('commission-rules/setting'), [
            'eligible_roles' => ['doctor', 'therapist', 'cashier'],
        ])->assertOk();

        $this->assertEqualsWithDelta(50_000, $this->rowFor($kasir->id)['total'], 0.01);
    }

    public function test_an_empty_list_is_refused(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        // Tanpa satu pun peran, fee tidak pernah jatuh ke siapa pun — hampir
        // pasti bukan yang dimaksud, jadi ditolak alih-alih diam-diam nol.
        $this->putJson($this->tenantUrl('commission-rules/setting'), ['eligible_roles' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('eligible_roles');
    }

    public function test_only_someone_who_may_manage_expenses_can_change_it(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $this->actingAsClinicUser(ClinicRole::Therapist);

        $this->putJson($this->tenantUrl('commission-rules/setting'), [
            'eligible_roles' => ['doctor', 'therapist', 'cashier'],
        ])->assertForbidden();
    }
}
