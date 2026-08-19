<?php

namespace Tests\Feature\Expense;

use App\Actions\Commission\SaveCommissionRuleAction;
use App\Enums\ClinicRole;
use App\Enums\CommissionRuleType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CommissionRule;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
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

    /**
     * Klinik memberi dokter 15rb dan terapis 5rb per pasien. Aturan khusus
     * seseorang harus MENGGANTIKAN aturan umum sejenis; kalau ditambahkan,
     * dokter pulang membawa 20rb tanpa ada yang memutuskan begitu.
     */
    public function test_personal_rate_replaces_the_general_one(): void
    {
        $this->seedRules();
        $doctor = $this->makeDoctor();

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee dokter',
            'therapist_id' => $doctor->id,
            'type' => 'per_patient',
            'amount' => 15000,
        ]);

        $this->sale(100_000, '2026-05-02', null, $doctor);

        $rows = collect((new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'])
            ->keyBy('therapist_name');

        $perPatient = collect($rows['dr. Andi']['lines'])
            ->firstWhere('type', CommissionRuleType::PerPatient);

        $this->assertSame(15000.0, $perPatient['amount']);
        $this->assertCount(
            1,
            collect($rows['dr. Andi']['lines'])->where('type', CommissionRuleType::PerPatient),
            'Fee umum dan fee khusus tidak boleh sama-sama terhitung.',
        );
    }

    public function test_general_rate_still_covers_staff_without_their_own(): void
    {
        $this->seedRules();
        $doctor = $this->makeDoctor();

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee dokter',
            'therapist_id' => $doctor->id,
            'type' => 'per_patient',
            'amount' => 15000,
        ]);

        $this->sale(100_000, '2026-05-02');

        $row = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'][0];
        $perPatient = collect($row['lines'])->firstWhere('type', CommissionRuleType::PerPatient);

        $this->assertSame('Jasmin', $row['therapist_name']);
        $this->assertSame(5000.0, $perPatient['amount']);
    }

    /**
     * Kebijakan fee berubah mendadak dan tanpa tanggal berlaku yang diketik
     * admin. Yang wajib dijaga: kenaikan hari ini tidak boleh ikut mengubah
     * kunjungan yang sudah lewat.
     */
    public function test_a_rate_change_leaves_earlier_visits_alone(): void
    {
        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee pasien',
            'type' => 'per_patient',
            'amount' => 5000,
        ]);

        $this->sale(100_000, '2026-05-02');

        // Tarif naik hari ini; kunjungan Mei di atas tetap 5rb.
        app(SaveCommissionRuleAction::class)->handle(
            ['name' => 'Fee pasien', 'type' => 'per_patient', 'amount' => 7000, 'percent' => 0, 'min_revenue' => 0, 'is_active' => true],
            $rule,
        );

        $row = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'][0];

        $this->assertSame(5000.0, collect($row['lines'])->firstWhere('type', CommissionRuleType::PerPatient)['amount']);
        $this->assertSame(2, CommissionRule::count(), 'Tarif lama harus tetap tersimpan sebagai riwayat.');
    }

    public function test_a_visit_after_the_change_uses_the_new_rate(): void
    {
        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee pasien',
            'type' => 'per_patient',
            'amount' => 5000,
        ]);

        app(SaveCommissionRuleAction::class)->handle(
            ['name' => 'Fee pasien', 'type' => 'per_patient', 'amount' => 7000, 'percent' => 0, 'min_revenue' => 0, 'is_active' => true],
            $rule,
        );

        // Kunjungan besok: sesudah tarif diganti.
        $this->sale(100_000, now()->addDay()->format('Y-m-d'));

        $result = (new CommissionCalculator(
            now()->addDay()->format('Y-m-d'),
            now()->addDay()->format('Y-m-d'),
        ))->run();

        $this->assertSame(
            7000.0,
            collect($result['rows'][0]['lines'])->firstWhere('type', CommissionRuleType::PerPatient)['amount'],
        );
    }

    public function test_renaming_a_rule_does_not_create_a_new_version(): void
    {
        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee pasien',
            'type' => 'per_patient',
            'amount' => 5000,
        ]);

        app(SaveCommissionRuleAction::class)->handle(
            ['name' => 'Fee per kunjungan', 'type' => 'per_patient', 'amount' => 5000, 'percent' => 0, 'min_revenue' => 0, 'is_active' => true],
            $rule,
        );

        $this->assertSame(1, CommissionRule::count());
        $this->assertSame('Fee per kunjungan', $rule->fresh()->name);
    }

    public function test_new_patient_without_a_recorded_referrer_pays_no_bonus(): void
    {
        $this->seedRules();

        // Pasien baru tanpa pembawa: fee kunjungannya tetap keluar, bonusnya
        // tidak. Dulu bonus ini jatuh ke terapis yang kebetulan melayani.
        $this->sale(100_000, '2026-05-02');

        $row = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'][0];

        $this->assertSame(0, $row['new_patients']);
        $this->assertNull(
            collect($row['lines'])->firstWhere('type', CommissionRuleType::PerNewPatient),
        );
    }

    /**
     * Menyimpan aturan lewat API sempat gagal 500: CommissionRule belum
     * terdaftar di morph map, sementara pencatatan audit menjadikannya
     * subjek. Tidak ada test yang menyentuh jalur ini, jadi lolos begitu saja.
     */
    public function test_rule_can_be_created_through_the_endpoint(): void
    {
        $response = $this->postJson($this->tenantUrl('commission-rules'), [
            'name' => 'Fee dokter',
            'type' => 'per_patient',
            'amount' => 15000,
            'percent' => 0,
            'min_revenue' => 0,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('commission_rules', [
            'name' => 'Fee dokter',
            'amount' => 15000,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_rule_can_be_updated_through_the_endpoint(): void
    {
        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee pasien',
            'type' => 'per_patient',
            'amount' => 5000,
        ]);

        $this->putJson($this->tenantUrl('commission-rules/'.$rule->id), [
            'name' => 'Fee pasien',
            'type' => 'per_patient',
            'amount' => 7000,
            'percent' => 0,
            'min_revenue' => 0,
            'is_active' => true,
        ])->assertOk();

        // Tarif lama ditutup, bukan ditimpa.
        $this->assertNotNull($rule->fresh()->effective_to);
        $this->assertSame(2, CommissionRule::count());
    }

    /**
     * Skenario yang diminta klinik: satu pasien facial ditangani satu terapis
     * dan satu dokter. Terapis dapat 5rb, dokter 15rb — keduanya, bukan salah
     * satu, dan bukan dibagi dua.
     */
    public function test_one_visit_pays_every_performer_their_own_fee(): void
    {
        $this->seedRules();
        $doctor = $this->makeDoctor();

        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee dokter',
            'therapist_id' => $doctor->id,
            'type' => 'per_patient',
            'amount' => 15000,
        ]);

        $sale = $this->sale(300_000, '2026-05-02');
        $sale->syncPerformers([$this->therapist->id, $doctor->id]);

        $rows = collect((new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'])
            ->keyBy('therapist_name');

        $feeOf = fn (string $name) => collect($rows[$name]['lines'])
            ->firstWhere('type', CommissionRuleType::PerPatient)['amount'];

        $this->assertSame(5000.0, $feeOf('Jasmin'));
        $this->assertSame(15000.0, $feeOf('dr. Andi'));
        $this->assertSame(1, $rows['Jasmin']['visits']);
        $this->assertSame(1, $rows['dr. Andi']['visits']);
    }

    /**
     * Target penjualan mengikuti penawar tiap baris, bukan pelaksananya.
     * Booster yang ditawarkan dokter masuk target dokter walau terapis yang
     * mengerjakan treatmentnya.
     */
    public function test_sales_target_follows_the_offering_staff_per_line(): void
    {
        $this->seedRules();
        $doctor = $this->makeDoctor();

        $sale = $this->sale(200_000, '2026-05-02');
        $sale->syncPerformers([$this->therapist->id, $doctor->id]);

        // Booster ditawarkan dokter di kunjungan yang sama.
        $booster = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 100_000]);
        $sale->items()->create([
            'service_id' => $booster->id,
            'offered_by' => $doctor->id,
            'name' => $booster->name,
            'unit_price' => 100_000,
            'qty' => 1,
            'subtotal' => 100_000,
        ]);

        $rows = collect((new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'])
            ->keyBy('therapist_name');

        // Terapis: hanya baris treatment yang ia tawarkan.
        $this->assertEqualsWithDelta(200_000, $rows['Jasmin']['revenue'], 0.01);
        // Dokter: hanya boosternya.
        $this->assertEqualsWithDelta(100_000, $rows['dr. Andi']['revenue'], 0.01);
    }

    public function test_a_line_nobody_offered_counts_toward_no_target(): void
    {
        $this->seedRules();

        $sale = $this->sale(200_000, '2026-05-02');
        $sale->syncPerformers([$this->therapist->id]);

        // Pasien mengambil sendiri dari etalase: tidak ada penawarnya.
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sunscreen',
            'unit' => 'pcs',
            'price' => 150_000,
            'stock_balance' => 5,
            'status' => 'active',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'offered_by' => null,
            'name' => $product->name,
            'unit_price' => 150_000,
            'qty' => 1,
            'subtotal' => 150_000,
        ]);

        $row = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'][0];

        // Omzetnya berhenti di 200rb; 150rb tanpa penawar tidak masuk ke mana pun.
        $this->assertEqualsWithDelta(200_000, $row['revenue'], 0.01);
    }

    private function makeDoctor(): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'dr. Andi',
            'email' => 'andi@klinik.test',
            'password' => bcrypt('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Doctor,
        ]);
    }

    private function makeTherapist(string $name = 'Jasmin'): User
    {
        return User::factory()->create([
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

    /**
     * Kunjungan treatment: transaksi dengan satu baris layanan, seperti yang
     * dihasilkan kasir. Fee per pasien memang hanya berlaku untuk ini.
     */
    private function sale(float $amount, string $date, ?Patient $patient = null, ?User $therapist = null): Transaction
    {
        $patient ??= Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $staff = $therapist ?? $this->therapist;

        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal' => $amount,
            'paid_amount' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'issued_at' => $date.' 10:00:00',
        ]);

        // Yang mengerjakan sekaligus yang menawarkan: bentuk paling lazim,
        // dan sama dengan cara data lama dipindahkan.
        $transaction->syncPerformers([$staff->id]);

        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => $amount,
        ]);

        $transaction->items()->create([
            'service_id' => $service->id,
            'offered_by' => $staff->id,
            'name' => $service->name,
            'unit_price' => $amount,
            'qty' => 1,
            'subtotal' => $amount,
        ]);

        return $transaction;
    }

    public function test_counts_fee_per_visit_and_new_patient_bonus(): void
    {
        $this->seedRules();

        // Pembawa dicatat: bonus pasien baru hanya untuk yang membawa,
        // tidak lagi jatuh ke terapis kunjungan pertama secara diam-diam.
        $returning = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'referred_by' => $this->therapist->id,
        ]);
        // Kunjungan pertama pasien ini jatuh sebelum periode -> bukan pasien baru.
        $this->sale(100_000, '2026-04-10', $returning);

        $this->sale(1_000_000, '2026-05-02', $returning);
        $this->sale(1_000_000, '2026-05-05', Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'referred_by' => $this->therapist->id,
        ]));

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
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'referred_by' => $this->therapist->id,
        ]);

        // Kunjungan pertama Mei -> bonus di Mei.
        $this->sale(100_000, '2026-05-02', $patient);
        // Kunjungan kedua Juni -> TIDAK ada bonus pasien baru lagi.
        $this->sale(100_000, '2026-06-02', $patient);

        $mei = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'][0];
        $juni = (new CommissionCalculator('2026-06-01', '2026-06-30'))->run()['rows'][0];

        $this->assertSame(1, $mei['new_patients']);
        $this->assertSame(0, $juni['new_patients']);
    }

    public function test_product_only_sale_earns_no_per_patient_fee(): void
    {
        $this->seedRules();

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'CyteaPro',
            'unit' => 'pcs',
            'price' => 350_000,
            'stock_balance' => 5,
            'status' => 'active',
        ]);

        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        // Transaksi berisi produk saja — bukan tindakan terapis, jadi dibuat
        // langsung tanpa helper yang selalu menambahkan baris layanan.
        $sale = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal' => 350_000,
            'paid_amount' => 0,
            'payment_status' => PaymentStatus::Unpaid,
            'issued_at' => '2026-05-10 10:00:00',
        ]);

        $sale->syncPerformers([$this->therapist->id]);

        $sale->items()->create([
            'product_id' => $product->id,
            'offered_by' => $this->therapist->id,
            'name' => $product->name,
            'unit_price' => 350_000,
            'qty' => 1,
            'subtotal' => 350_000,
        ]);

        $row = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'][0];

        // Tidak ada fee per pasien, tapi omzetnya tetap masuk komisi penjualan.
        $this->assertSame(0, $row['visits']);
        $perPatient = collect($row['lines'])->firstWhere('type', CommissionRuleType::PerPatient);
        $this->assertNull($perPatient);
        $this->assertEqualsWithDelta(350_000, $row['revenue'], 0.01);
    }

    public function test_visit_with_treatment_still_earns_fee_even_when_products_added(): void
    {
        $this->seedRules();

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Vita B Gel', 'unit' => 'pcs',
            'price' => 70_000, 'stock_balance' => 5, 'status' => 'active',
        ]);

        $sale = $this->sale(170_000, '2026-05-10');
        $sale->items()->createMany([
            ['service_id' => $service->id, 'name' => $service->name, 'unit_price' => 100_000, 'qty' => 1, 'subtotal' => 100_000],
            ['product_id' => $product->id, 'name' => $product->name, 'unit_price' => 70_000, 'qty' => 1, 'subtotal' => 70_000],
        ]);

        $row = (new CommissionCalculator('2026-05-01', '2026-05-31'))->run()['rows'][0];

        $this->assertSame(1, $row['visits']);
        $perPatient = collect($row['lines'])->firstWhere('type', CommissionRuleType::PerPatient);
        $this->assertEqualsWithDelta(5000, $perPatient['amount'], 0.01);
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
