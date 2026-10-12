<?php

namespace Tests\Feature\Report;

use App\Enums\ClinicRole;
use App\Enums\CommissionRuleType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exports\MonthlyReportExport;
use App\Models\CommissionRule;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Upselling harus terbaca, bukan hanya terhitung.
 *
 * Komisi penjualan sudah lama dihitung benar, tapi di laporan ia melebur jadi
 * satu angka total per orang: tidak ada omzetnya, tidak ada targetnya, dan
 * tidak ada keterangan sudah tercapai atau belum. Admin melihat satu nominal
 * tanpa cara apa pun menjelaskannya ke terapis yang menanyakannya.
 */
class UpsellReportVisibilityTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const TARGET = 3_000_000;

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

    /** Dua orang: satu sudah tembus target, satu belum. */
    private function twoSellers(): User
    {
        $admin = $this->actingAsClinicUser(ClinicRole::Admin);
        $this->rules();

        $tembus = $this->seller('Rina Tembus');
        $belum = $this->seller('Sari Belum');

        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->sell($tembus, 4_000_000);
        $this->sell($belum, 1_000_000);

        return $admin;
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        return app(ReportService::class)->monthly(
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        );
    }

    public function test_the_api_carries_the_omzet_target_and_commission_per_person(): void
    {
        // Admin yang sama dipakai lagi; membuat admin kedua menabrak
        // keunikan emailnya.
        $this->actingAs($this->twoSellers());

        $response = $this->getJson($this->tenantUrl(
            'reports/monthly?from='.now()->startOfMonth()->toDateString()
                .'&to='.now()->endOfMonth()->toDateString()
        ))->assertOk();

        $rows = collect($response->json('data.commission.rows'));
        $tembus = $rows->firstWhere('therapist_name', 'Rina Tembus');
        $belum = $rows->firstWhere('therapist_name', 'Sari Belum');

        $this->assertEqualsWithDelta(4_000_000, $tembus['revenue'], 0.01);
        $this->assertEqualsWithDelta(200_000, $tembus['commission_amount'], 0.01);
        $this->assertEqualsWithDelta(5_000, $tembus['fee_amount'], 0.01);
        $this->assertTrue($tembus['upsell_reached']);

        // Yang belum tercapai tetap membawa targetnya, supaya laporan bisa
        // menyebut kurang berapa lagi alih-alih hanya "Rp0".
        $this->assertEqualsWithDelta(1_000_000, $belum['revenue'], 0.01);
        $this->assertEqualsWithDelta(0, $belum['commission_amount'], 0.01);
        $this->assertEqualsWithDelta(self::TARGET, $belum['upsell_target'], 0.01);
        $this->assertFalse($belum['upsell_reached']);
    }

    public function test_the_spreadsheet_has_an_upselling_block(): void
    {
        $this->twoSellers();

        $sheet = (new MonthlyReportExport($this->report(), 'Meba Clinic'))
            ->build()
            ->getActiveSheet();

        $this->assertNotNull(
            $this->findRow($sheet, 'UPSELLING PENJUALAN (OMZET YANG DITAWARKAN STAF)'),
            'Blok upselling tidak ada di lembar laporan.',
        );
    }

    public function test_the_spreadsheet_states_omzet_status_and_commission(): void
    {
        $this->twoSellers();

        $sheet = (new MonthlyReportExport($this->report(), 'Meba Clinic'))
            ->build()
            ->getActiveSheet();

        $tembus = $this->findRow($sheet, 'Rina Tembus');
        $belum = $this->findRow($sheet, 'Sari Belum');

        $this->assertNotNull($tembus);
        $this->assertNotNull($belum);

        $this->assertEqualsWithDelta(4_000_000, (float) $sheet->getCell("D{$tembus}")->getValue(), 0.01);
        $this->assertEqualsWithDelta(self::TARGET, (float) $sheet->getCell("E{$tembus}")->getValue(), 0.01);
        $this->assertSame('Tercapai', $sheet->getCell("F{$tembus}")->getValue());
        $this->assertSame('5%', $sheet->getCell("G{$tembus}")->getValue());
        $this->assertEqualsWithDelta(200_000, (float) $sheet->getCell("H{$tembus}")->getValue(), 0.01);

        // Kurang 2 juta lagi — angka itu yang dicari admin, bukan sekadar Rp0.
        $this->assertSame('Kurang Rp2.000.000', $sheet->getCell("F{$belum}")->getValue());
        $this->assertEqualsWithDelta(0, (float) $sheet->getCell("H{$belum}")->getValue(), 0.01);
    }

    /**
     * Lembar PDF memakai sumber data yang sama tapi templat sendiri, jadi ia
     * bisa patah atau ketinggalan tanpa terlihat dari dua permukaan lainnya.
     */
    public function test_the_pdf_layout_shows_the_same_upselling_block(): void
    {
        $this->twoSellers();

        $html = view('reports.monthly', [
            'report' => $this->report(),
            'clinicName' => 'Meba Clinic',
        ])->render();

        $this->assertStringContainsString('UPSELLING PENJUALAN', $html);
        $this->assertStringContainsString('Rina Tembus', $html);
        $this->assertStringContainsString('Tercapai', $html);
        $this->assertStringContainsString('Kurang Rp2.000.000', $html);
        $this->assertStringContainsString('TOTAL KOMISI UPSELLING', $html);
    }

    /** Nomor baris pertama di kolom B yang isinya persis teks ini. */
    private function findRow(Worksheet $sheet, string $text): ?int
    {
        foreach ($sheet->getRowIterator() as $row) {
            if ($sheet->getCell('B'.$row->getRowIndex())->getValue() === $text) {
                return $row->getRowIndex();
            }
        }

        return null;
    }
}
