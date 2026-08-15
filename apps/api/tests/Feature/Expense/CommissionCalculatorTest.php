<?php

namespace Tests\Feature\Expense;

use App\Enums\ClinicRole;
use App\Enums\CommissionRuleType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CommissionRule;
use App\Models\Patient;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Empat skema fee yang dipakai klinik dijadikan data, jadi yang diuji di sini
 * adalah apakah data itu benar-benar menghasilkan angka yang sama dengan
 * hitungan manual di laporan bulanan.
 */
class CommissionCalculatorTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private User $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser(ClinicRole::Admin);
        CommissionRule::query()->delete();
        $this->therapist = $this->makeTherapist();
    }

    private function makeTherapist(string $name = 'Jasmin'): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'email' => strtolower($name).'@klinik.test',
            'password' => bcrypt('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Therapist,
        ]);
    }

    /** Empat aturan sesuai skema klinik. */
    private function seedRules(): void
    {
        CommissionRule::create(['tenant_id' => $this->tenant->id, 'name' => 'Fee pasien', 'type' => 'per_patient', 'amount' => 5000]);
        CommissionRule::create(['tenant_id' => $this->tenant->id, 'name' => 'Bonus pasien baru', 'type' => 'per_new_patient', 'amount' => 5000]);
        CommissionRule::create(['tenant_id' => $this->tenant->id, 'name' => 'Target penjualan', 'type' => 'revenue_percent', 'percent' => 5, 'min_revenue' => 0]);
        CommissionRule::create(['tenant_id' => $this->tenant->id, 'name' => 'Omzet 10 juta ke atas', 'type' => 'revenue_percent', 'percent' => 6, 'min_revenue' => 10_000_000]);
    }

    private function sale(float $amount, string $date, ?Patient $patient = null, ?User $therapist = null): Transaction
    {
        $patient ??= Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        return Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'therapist_id' => ($therapist ?? $this->therapist)->id,
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal' => $amount,
            'paid_amount' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'issued_at' => $date.' 10:00:00',
        ]);
    }

    public function test_counts_fee_per_visit_and_new_patient_bonus(): void
    {
        $this->seedRules();

        $returning = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        // Kunjungan pertama pasien ini jatuh sebelum periode -> bukan pasien baru.
        $this->sale(100_000, '2026-04-10', $returning);

        $this->sale(1_000_000, '2026-05-02', $returning);
        $this->sale(1_000_000, '2026-05-05');

        $result = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run();
        $row = $result['rows'][0];

        $this->assertSame(2, $row['visits']);
        $this->assertSame(1, $row['new_patients']);
        // 2 kunjungan x 5.000 + 1 pasien baru x 5.000 + 5% dari 2.000.000
        $this->assertEqualsWithDelta(5000 * 2 + 5000 + 100_000, $row['total'], 0.01);
    }

    public function test_uses_five_percent_below_ten_million(): void
    {
        $this->seedRules();
        $this->sale(8_000_000, '2026-05-10');

        $row = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'][0];
        $percentLine = collect($row['lines'])->firstWhere('type', CommissionRuleType::RevenuePercent);

        $this->assertSame('Target penjualan', $percentLine['rule_name']);
        $this->assertEqualsWithDelta(400_000, $percentLine['amount'], 0.01);
    }

    public function test_switches_to_six_percent_from_ten_million(): void
    {
        $this->seedRules();
        $this->sale(10_000_000, '2026-05-10');

        $row = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'][0];
        $percentLine = collect($row['lines'])->firstWhere('type', CommissionRuleType::RevenuePercent);

        $this->assertSame('Omzet 10 juta ke atas', $percentLine['rule_name']);
        $this->assertEqualsWithDelta(600_000, $percentLine['amount'], 0.01);
    }

    public function test_tiers_never_stack(): void
    {
        $this->seedRules();
        $this->sale(12_000_000, '2026-05-10');

        $row = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'][0];
        $percentLines = collect($row['lines'])
            ->where('type', CommissionRuleType::RevenuePercent);

        // Hanya satu baris persentase yang boleh muncul, bukan 5% + 6%.
        $this->assertCount(1, $percentLines);
        $this->assertEqualsWithDelta(720_000, $percentLines->first()['amount'], 0.01);
    }

    public function test_inactive_rule_is_ignored(): void
    {
        CommissionRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Fee pasien',
            'type' => 'per_patient', 'amount' => 5000, 'is_active' => false,
        ]);
        $this->sale(1_000_000, '2026-05-10');

        $result = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run();

        $this->assertSame(0.0, $result['total']);
    }

    public function test_splits_per_therapist(): void
    {
        $this->seedRules();
        $other = $this->makeTherapist('Rani');

        $this->sale(1_000_000, '2026-05-02');
        $this->sale(3_000_000, '2026-05-03', null, $other);

        $result = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run();

        $this->assertCount(2, $result['rows']);
        // Diurutkan menurun, jadi terapis dengan omzet terbesar di atas.
        $this->assertSame('Rani', $result['rows'][0]['therapist_name']);
    }

    public function test_ignores_transactions_outside_period_and_cancelled(): void
    {
        $this->seedRules();

        $this->sale(1_000_000, '2026-04-30');
        $this->sale(1_000_000, '2026-06-01');
        $cancelled = $this->sale(5_000_000, '2026-05-10');
        $cancelled->update(['cancelled_at' => now()]);

        $result = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run();

        $this->assertSame([], $result['rows']);
        $this->assertSame(0.0, $result['total']);
    }

    public function test_new_patient_bonus_goes_to_referrer_not_therapist(): void
    {
        $this->seedRules();
        $receptionist = $this->makeTherapist('Resepsionis');

        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'referred_by' => $receptionist->id,
        ]);

        // Kunjungan pertama ditangani Jasmin, tapi yang membawa Resepsionis.
        $this->sale(1_000_000, '2026-05-02', $patient);

        $rows = collect((new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows']);

        // Bonus pasien baru (5.000) milik pembawa, bukan terapis.
        $this->assertEqualsWithDelta(5000, $rows->firstWhere('therapist_name', 'Resepsionis')['total'], 0.01);
        $this->assertSame(1, $rows->firstWhere('therapist_name', 'Resepsionis')['new_patients']);
        $this->assertSame(0, $rows->firstWhere('therapist_name', 'Jasmin')['new_patients']);
    }

    public function test_new_patient_counted_once_across_periods(): void
    {
        $this->seedRules();
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        // Kunjungan pertama Mei -> bonus di Mei.
        $this->sale(100_000, '2026-05-02', $patient);
        // Kunjungan kedua Juni -> TIDAK ada bonus pasien baru lagi.
        $this->sale(100_000, '2026-06-02', $patient);

        $mei = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'][0];
        $juni = (new CommissionCalculator('2026-06-01', '2026-06-30'))->run()['rows'][0];

        $this->assertSame(1, $mei['new_patients']);
        $this->assertSame(0, $juni['new_patients']);
    }

    public function test_endpoint_returns_preview(): void
    {
        $this->seedRules();
        $this->sale(2_000_000, '2026-05-10');

        $this->getJson($this->tenantUrl('commission-rules/calculate?from=2026-05-01&to=2026-05-31'))
            ->assertOk()
            ->assertJsonPath('data.rows.0.therapist_name', 'Jasmin');
    }

    public function test_cashier_cannot_read_commission_rules(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->getJson($this->tenantUrl('commission-rules'))->assertForbidden();
    }
}
