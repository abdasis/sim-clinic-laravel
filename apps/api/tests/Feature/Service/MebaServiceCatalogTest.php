<?php

namespace Tests\Feature\Service;

use App\Enums\ClinicRole;
use App\Enums\PaymentStatus;
use App\Models\Patient;
use App\Models\Promo;
use App\Models\PromoItem;
use App\Models\Service;
use App\Models\Transaction;
use App\Support\CommissionCalculator;
use Database\Seeders\MebaServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Katalog treatment final. Yang diuji bukan sekadar "30 baris masuk",
 * melainkan bahwa seed ulang tidak menggandakan, treatment percobaan
 * benar-benar lenyap dari master, dan riwayat tindakan lama tetap utuh.
 */
class MebaServiceCatalogTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const EXPECTED_TOTAL = 30;

    private function seedCatalog(): void
    {
        app(MebaServiceSeeder::class)->seedTenant($this->tenant);
        app()->instance('tenant', $this->tenant);
    }

    public function test_seeds_exactly_thirty_services_with_categories(): void
    {
        $this->actingAsClinicUser();
        $this->seedCatalog();

        $this->assertSame(self::EXPECTED_TOTAL, Service::query()->count());

        $perCategory = Service::query()->get()
            ->groupBy(fn (Service $service) => $service->assignedCategory()?->name)
            ->map->count();

        $this->assertCount(4, $perCategory);
        $this->assertSame(13, $perCategory['Skinbooster']);
        $this->assertSame(5, $perCategory['Derma Treatment']);
        $this->assertSame(8, $perCategory['Facial & Peeling']);
        $this->assertSame(4, $perCategory['Treatment Lainnya']);
    }

    public function test_prices_match_the_official_pricelist(): void
    {
        $this->actingAsClinicUser();
        $this->seedCatalog();

        $expected = [
            'Skinbooster Collagen' => 3_000_000,
            'Skinbooster Cellbooster' => 3_500_000,
            'Skinbooster Acne Killer' => 750_000,
            'Derma Porzelan' => 1_400_000,
            'Facial Basic' => 100_000,
            'PDT' => 80_000,
            'Meso Chubu / Double Chin' => 450_000,
            'Biopen EMS Treatment' => 750_000,
        ];

        foreach ($expected as $name => $price) {
            $service = Service::query()->where('name', $name)->firstOrFail();

            $this->assertEqualsWithDelta($price, (float) $service->price, 0.01, $name);
            $this->assertSame('active', $service->status->value, $name);
        }
    }

    public function test_acne_killer_appears_only_once(): void
    {
        $this->actingAsClinicUser();
        $this->seedCatalog();

        // Pricelist menuliskannya dua kali dengan harga sama; masternya satu.
        $this->assertSame(1, Service::query()->where('name', 'Skinbooster Acne Killer')->count());
    }

    public function test_seeding_repeatedly_does_not_duplicate(): void
    {
        $this->actingAsClinicUser();

        $this->seedCatalog();
        $this->seedCatalog();
        $this->seedCatalog();

        $this->assertSame(self::EXPECTED_TOTAL, Service::query()->count());
        $this->assertSame(1, Service::query()->where('name', 'Derma Pink Glow')->count());
    }

    public function test_updates_existing_service_instead_of_duplicating(): void
    {
        $this->actingAsClinicUser();

        // Facial Basic sudah ada dengan harga lama dan durasi yang sudah
        // disesuaikan operator.
        Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Basic',
            'price' => 150_000,
            'duration_minutes' => 45,
            'status' => 'active',
        ]);

        $this->seedCatalog();

        $service = Service::query()->where('name', 'Facial Basic')->firstOrFail();

        $this->assertSame(1, Service::query()->where('name', 'Facial Basic')->count());
        $this->assertEqualsWithDelta(100_000, (float) $service->price, 0.01);
        $this->assertSame('Facial & Peeling', $service->assignedCategory()?->name);
        // Durasi tidak ada di pricelist, jadi yang sudah diatur tidak ditimpa.
        $this->assertSame(45, $service->duration_minutes);
    }

    public function test_removes_unused_dummy_services(): void
    {
        $this->actingAsClinicUser();

        Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Treatment Percobaan',
            'price' => 1_000,
            'status' => 'active',
        ]);

        $this->seedCatalog();

        $this->assertSame(0, Service::query()->where('name', 'Treatment Percobaan')->count());
        $this->assertSame(self::EXPECTED_TOTAL, Service::query()->count());
    }

    public function test_archives_dummy_service_that_has_transaction_history(): void
    {
        $this->actingAsClinicUser();

        $dummy = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chemical Peeling',
            'price' => 350_000,
            'status' => 'active',
        ]);

        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-LAMA-9',
            'subtotal' => 350_000,
            'paid_amount' => 350_000,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => now(),
        ]);

        $transaction->items()->create([
            'service_id' => $dummy->id,
            'name' => $dummy->name,
            'unit_price' => 350_000,
            'qty' => 1,
            'subtotal' => 350_000,
        ]);

        $this->seedCatalog();

        // Riwayat tindakannya wajib utuh.
        $this->assertDatabaseHas('transaction_items', [
            'service_id' => $dummy->id,
            'name' => 'Chemical Peeling',
        ]);

        // Tapi layanannya tidak lagi aktif di master.
        $this->assertSame('archived', $dummy->fresh()->status->value);
        $this->assertSame(self::EXPECTED_TOTAL, Service::query()->where('status', 'active')->count());
    }

    public function test_archives_dummy_service_that_is_targeted_by_a_promo(): void
    {
        $this->actingAsClinicUser();

        $dummy = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Promo Percobaan',
            'price' => 200_000,
            'status' => 'active',
        ]);

        $promo = Promo::factory()->create(['tenant_id' => $this->tenant->id]);
        PromoItem::create([
            'tenant_id' => $this->tenant->id,
            'promo_id' => $promo->id,
            'promotable_type' => Service::class,
            'promotable_id' => $dummy->id,
        ]);

        $this->seedCatalog();

        // Promo memakai relasi morph tanpa foreign key: menghapus layanannya
        // tidak ditolak database, tapi meninggalkan promo yang menunjuk ke
        // ruang kosong.
        $this->assertSame('archived', $dummy->fresh()->status->value);
        $this->assertDatabaseHas('promo_items', [
            'promotable_type' => Service::class,
            'promotable_id' => $dummy->id,
        ]);
    }

    public function test_master_data_endpoint_lists_the_thirty_with_indonesian_labels(): void
    {
        $this->actingAsClinicUser();
        $this->seedCatalog();

        $response = $this->getJson($this->tenantUrl('services?per_page=100'))->assertOk();

        $this->assertCount(self::EXPECTED_TOTAL, $response->json('data'));
        $this->assertSame(self::EXPECTED_TOTAL, $response->json('meta.total'));

        $rows = collect($response->json('data'));

        $this->assertSame('Treatment Lainnya', $rows->firstWhere('name', 'Botox Full Face')['category_label']);
        $this->assertSame('Facial & Peeling', $rows->firstWhere('name', 'Oxi Infusion')['category_label']);
        $this->assertSame('85000.00', $rows->firstWhere('name', 'Oxi Infusion')['price']);
    }

    public function test_services_are_sellable_at_pos_using_master_price(): void
    {
        $this->actingAsClinicUser();
        $this->seedCatalog();

        $service = Service::query()->where('name', 'Facial Basic')->firstOrFail();
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['service_id' => $service->id, 'qty' => 1]],
        ])
            ->assertCreated()
            // Harga diambil dari master layanan, bukan kiriman klien.
            ->assertJsonPath('data.items.0.unit_price', '100000.00')
            ->assertJsonPath('data.subtotal', '100000.00');
    }

    public function test_treatment_visit_earns_the_five_thousand_therapist_fee(): void
    {
        $this->actingAsClinicUser();
        $therapist = $this->actingAsClinicUser(ClinicRole::Therapist);
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->seedCatalog();

        $service = Service::query()->where('name', 'Facial Basic')->firstOrFail();
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'performer_ids' => [$therapist->id],
            'items' => [['service_id' => $service->id, 'qty' => 1, 'offered_by' => $therapist->id]],
        ])->assertCreated();

        $result = (new CommissionCalculator(now()->toDateString(), now()->toDateString()))->run();

        $row = collect($result['rows'])->firstWhere('therapist_id', $therapist->id);

        $this->assertSame(1, $row['visits']);
        // Fee Rp5.000 terpisah dari harga treatment Rp100.000.
        $fee = collect($row['lines'])->firstWhere('rule_name', 'Fee pasien');
        $this->assertEqualsWithDelta(5_000, $fee['amount'], 0.01);
        $this->assertEqualsWithDelta(100_000, $row['revenue'], 0.01);
    }

    public function test_catalog_does_not_leak_into_other_tenants(): void
    {
        $this->actingAsClinicUser();

        $other = $this->createTenant('klinik-tetangga');
        Service::create([
            'tenant_id' => $other->id,
            'name' => 'Treatment Klinik Tetangga',
            'price' => 50_000,
            'status' => 'active',
        ]);

        $this->seedCatalog();

        $this->assertSame(self::EXPECTED_TOTAL, Service::query()->count());

        app()->instance('tenant', $other);
        $this->assertSame(1, Service::query()->count());
        $this->assertSame('Treatment Klinik Tetangga', Service::query()->first()->name);
    }

    public function test_seeder_does_not_invent_durations(): void
    {
        $this->actingAsClinicUser();
        $this->seedCatalog();

        // Pricelist tidak memuat durasi; layanan baru memakai default kolom
        // dan disesuaikan operator lewat form, bukan angka karangan seeder.
        $this->assertSame(
            self::EXPECTED_TOTAL,
            Service::query()->where('duration_minutes', 30)->count(),
        );
        $this->assertSame(0, Service::query()->whereNotNull('description')->count());
    }
}
