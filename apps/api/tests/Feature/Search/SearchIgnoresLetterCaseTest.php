<?php

namespace Tests\Feature\Search;

use App\Actions\Chatbot\GetProductInfoAction;
use App\Actions\Chatbot\GetServiceInfoAction;
use App\Actions\Chatbot\SearchServicesAction;
use App\Actions\Chatbot\SearchStaffAction;
use App\Actions\LogAuditAction;
use App\Enums\ClinicRole;
use App\Models\Category;
use App\Models\CompanyTreatment;
use App\Models\Expense;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Promo;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Setiap kotak pencarian abai besar-kecil huruf.
 *
 * LIKE di PostgreSQL — yang dipakai produksi — peka besar-kecil huruf, jadi
 * mengetik "siti" tidak pernah menemukan "Siti Rahayu". Cacatnya sama persis
 * di tujuh belas titik pencarian, dan lolos selama ini karena suite bawaan
 * berjalan di SQLite yang justru abai besar-kecil huruf.
 *
 * Karena itu tes ini baru bergigi lewat `phpunit.pgsql.xml`. Di SQLite ia
 * lulus tanpa membuktikan apa pun; di PostgreSQL ia yang menahan cacatnya
 * kembali.
 */
class SearchIgnoresLetterCaseTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
    }

    /**
     * @return array<int, int>
     */
    private function ids(string $url, string $keyword): array
    {
        return collect(
            $this->getJson($url.'?'.http_build_query(['search' => $keyword]))
                ->assertOk()
                ->json('data'),
        )->pluck('id')->all();
    }

    /**
     * Dicari dua arah: kata kunci huruf kecil atas data berkapital, lalu
     * sebaliknya. Satu arah saja bisa lulus karena kebetulan.
     *
     * @param  array<int, int>  $expected
     */
    private function assertFoundBothWays(string $url, string $lower, string $upper, array $expected): void
    {
        $this->assertSame($expected, $this->ids($url, $lower), "kata kunci huruf kecil: {$lower}");
        $this->assertSame($expected, $this->ids($url, $upper), "kata kunci huruf besar: {$upper}");
    }

    public function test_patient_search(): void
    {
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Siti Rahayu',
            'whatsapp' => '081234567890',
        ]);

        $this->assertFoundBothWays($this->tenantUrl('patients'), 'siti', 'RAHAYU', [$patient->id]);
    }

    public function test_staff_search(): void
    {
        $staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dokter Wulandari',
            'email' => 'Wulandari@klinik.test',
            'clinic_role' => ClinicRole::Doctor,
        ]);

        $this->assertFoundBothWays($this->tenantUrl('staff'), 'wulandari', 'DOKTER', [$staff->id]);
    }

    public function test_tenant_user_search(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Budi Santoso',
            'email' => 'Budi@klinik.test',
        ]);

        // Manajemen user tenant tinggal di luar prefix /clinic.
        $url = '/api/'.$this->tenant->slug.'/users';

        $this->assertContains($user->id, $this->ids($url, 'santoso'));
        $this->assertContains($user->id, $this->ids($url, 'BUDI@KLINIK'));
    }

    public function test_category_and_unit_search(): void
    {
        $category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Perawatan Wajah',
            'type' => 'service',
            'status' => 'active',
        ]);
        $unit = Unit::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Botol Kecil',
            'status' => 'active',
        ]);

        $this->assertFoundBothWays($this->tenantUrl('categories'), 'wajah', 'PERAWATAN', [$category->id]);
        $this->assertFoundBothWays($this->tenantUrl('units'), 'botol', 'KECIL', [$unit->id]);
    }

    public function test_promo_search(): void
    {
        $promo = Promo::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Promo Kemerdekaan',
        ]);

        $this->assertFoundBothWays($this->tenantUrl('promos'), 'kemerdekaan', 'PROMO KEM', [$promo->id]);
    }

    public function test_transaction_search_by_invoice_number(): void
    {
        $transaction = Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-2026-AB99',
        ]);

        $this->assertFoundBothWays($this->tenantUrl('transactions'), 'inv-2026-ab', 'AB99', [$transaction->id]);
    }

    public function test_expense_search_covers_description_and_note(): void
    {
        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'description' => 'Beli Alkohol Swab',
            'note' => 'Dari Apotek Sehat',
        ]);

        $this->assertFoundBothWays($this->tenantUrl('expenses'), 'alkohol', 'APOTEK', [$expense->id]);
    }

    public function test_medical_record_search_by_patient_name(): void
    {
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ratna Kusuma',
        ]);
        $record = MedicalRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'author_id' => auth()->id(),
        ]);

        $this->assertFoundBothWays($this->tenantUrl('medical-records'), 'ratna', 'KUSUMA', [$record->id]);
    }

    /** Log dicari lewat narasinya, jenis peristiwanya, dan nama pelakunya. */
    public function test_activity_log_search(): void
    {
        // Dibuat lewat LogAuditAction, jalur yang dipakai seluruh aplikasi:
        // menulis barisnya langsung menyimpan causer_type sebagai nama kelas
        // penuh, sementara data sungguhan memakai alias morph — dan
        // pencarian lewat nama pelaku hanya terbaca pada bentuk yang asli.
        $activity = app(LogAuditAction::class)->handle(
            'service.created',
            null,
            auth()->user(),
            [],
            'Menambahkan layanan Facial Glow.',
        );

        $url = $this->tenantUrl('activity-logs');

        $this->assertSame([$activity->id], $this->ids($url, 'facial glow'));
        $this->assertSame([$activity->id], $this->ids($url, 'MENAMBAHKAN'));
        $this->assertSame([$activity->id], $this->ids($url, 'SERVICE.CREATED'));
        // Nama pelaku ikut dicari lewat relasinya.
        $this->assertSame([$activity->id], $this->ids($url, mb_strtoupper(auth()->user()->name)));
    }

    public function test_company_profile_content_search(): void
    {
        $treatment = CompanyTreatment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'title' => ['id' => 'Perawatan Kulit Kering', 'en' => 'Dry Skin Care'],
        ]);

        // Judulnya kolom json multi-bahasa; dicari sebagai teks mentah.
        $this->assertFoundBothWays(
            $this->tenantUrl('company-profile/treatments'),
            'kulit kering',
            'DRY SKIN',
            [$treatment->id],
        );
    }

    /**
     * Chatbot menjawab pasien yang mengetik seenaknya di WhatsApp — di sanalah
     * besar-kecil huruf paling tidak bisa diandalkan.
     */
    public function test_chatbot_actions_search(): void
    {
        Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Glow',
            'price' => 150000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Serum Vitamin C',
            'status' => 'active',
        ]);
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Terapis Maryam',
            'clinic_role' => ClinicRole::Therapist,
        ]);

        $this->assertNotEmpty(app(SearchServicesAction::class)->handle('FACIAL'));
        $this->assertNotEmpty(app(GetServiceInfoAction::class)->handle('glow'));
        $this->assertNotEmpty(app(GetProductInfoAction::class)->handle('SERUM'));
        $this->assertNotEmpty(app(SearchStaffAction::class)->handle('maryam'));
    }
}
