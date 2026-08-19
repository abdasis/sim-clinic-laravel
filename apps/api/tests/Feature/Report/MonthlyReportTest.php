<?php

namespace Tests\Feature\Report;

use App\Enums\ClinicRole;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CommissionRule;
use App\Models\Expense;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Laporan bulanan menggabungkan pendapatan, dana, pengeluaran, dan fee —
 * angka yang dilaporkan ke pemilik klinik. Diuji terhadap skenario yang
 * meniru laporan spreadsheet aslinya.
 */
class MonthlyReportTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private User $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();

        // Tenant baru otomatis dapat empat aturan bawaan; tes ini menyetel
        // aturannya sendiri supaya angka yang diuji tidak tercampur.
        CommissionRule::query()->delete();

        $this->therapist = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jasmin',
            'email' => 'jasmin@klinik.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Therapist,
        ]);
    }

    /**
     * Transaksi lunas dengan satu item layanan dan satu item produk,
     * plus pembayaran tunai penuh.
     */
    private function paidSale(float $servicePrice, float $productPrice, string $date): Transaction
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => $servicePrice]);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'stock_balance' => 10, 'price' => $productPrice]);
        $total = $servicePrice + $productPrice;

        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-'.uniqid(),
            'subtotal' => $total,
            'paid_amount' => $total,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => $date.' 10:00:00',
        ]);

        $transaction->syncPerformers([$this->therapist->id]);

        $transaction->items()->createMany([
            ['service_id' => $service->id, 'offered_by' => $this->therapist->id, 'name' => $service->name, 'unit_price' => $servicePrice, 'qty' => 1, 'subtotal' => $servicePrice],
            ['product_id' => $product->id, 'offered_by' => $this->therapist->id, 'name' => $product->name, 'unit_price' => $productPrice, 'qty' => 1, 'subtotal' => $productPrice],
        ]);

        $transaction->payments()->create([
            'method' => 'cash',
            'amount' => $total,
            'paid_at' => $date.' 10:05:00',
            'received_by' => auth()->id(),
        ]);

        return $transaction;
    }

    public function test_monthly_report_combines_revenue_expense_and_net_profit(): void
    {
        $this->paidSale(200_000, 95_000, '2026-05-02');
        $this->paidSale(1_500_000, 250_000, '2026-05-12');

        Expense::factory()->create(['tenant_id' => $this->tenant->id, 'spent_at' => '2026-05-20', 'category' => 'operational', 'amount' => 500_000]);

        CommissionRule::create(['tenant_id' => $this->tenant->id, 'name' => 'Fee pasien', 'type' => 'per_patient', 'amount' => 5000]);

        $response = $this->getJson($this->tenantUrl('reports/monthly?from=2026-05-01&to=2026-05-31'))
            ->assertOk();

        $response->assertJsonPath('data.totals.treatment', 1_700_000)
            ->assertJsonPath('data.totals.product', 345_000)
            ->assertJsonPath('data.totals.revenue', 2_045_000)
            ->assertJsonPath('data.expenses.total', 500_000)
            ->assertJsonPath('data.commission.total', 10_000)
            // Bersih = pendapatan - pengeluaran; fee belum dibukukan.
            ->assertJsonPath('data.net_profit', 1_545_000)
            ->assertJsonPath('data.rows.0.therapist_name', 'Jasmin');

        $cash = collect($response->json('data.payments'))->firstWhere('method', 'cash');
        $this->assertEqualsWithDelta(2_045_000, $cash['total'], 0.01);
    }

    public function test_export_returns_xlsx_and_pdf(): void
    {
        $this->paidSale(200_000, 0, '2026-05-02');

        $this->get($this->tenantUrl('reports/monthly/export?from=2026-05-01&to=2026-05-31&format=xlsx'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->get($this->tenantUrl('reports/monthly/export?from=2026-05-01&to=2026-05-31&format=pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_forbidden_for_cashier(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->get($this->tenantUrl('reports/monthly/export?from=2026-05-01&to=2026-05-31'))
            ->assertForbidden();
    }

    public function test_therapist_scoped_rule_only_applies_to_that_therapist(): void
    {
        $other = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Rani',
            'email' => 'rani@klinik.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'clinic_role' => ClinicRole::Therapist,
        ]);

        // Aturan khusus Jasmin; Rani tidak boleh kecipratan.
        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fee khusus Jasmin',
            'therapist_id' => $this->therapist->id,
            'type' => 'per_patient',
            'amount' => 10_000,
        ]);

        $this->paidSale(100_000, 0, '2026-05-02');

        $sale = $this->paidSale(100_000, 0, '2026-05-03');
        // Kunjungan kedua dikerjakan Rani, bukan Jasmin.
        $sale->syncPerformers([$other->id]);
        $sale->items()->update(['offered_by' => $other->id]);

        $rows = collect($this->getJson(
            $this->tenantUrl('commission-rules/calculate?from=2026-05-01&to=2026-05-31'),
        )->assertOk()->json('data.rows'));

        $this->assertEqualsWithDelta(10_000, $rows->firstWhere('therapist_name', 'Jasmin')['total'], 0.01);
        $this->assertSame(0.0, (float) ($rows->firstWhere('therapist_name', 'Rani')['total'] ?? 0));
    }
}
