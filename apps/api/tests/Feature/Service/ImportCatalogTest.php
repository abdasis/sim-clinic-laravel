<?php

namespace Tests\Feature\Service;

use App\Actions\Import\ParseImportFileAction;
use App\Enums\CategorizableType;
use App\Enums\ClinicRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Impor menulis puluhan baris sekaligus. Yang dijaga: baris yang salah tidak
 * ikut menggugurkan baris yang benar, nama yang sudah ada diperbarui alih-alih
 * digandakan, dan kategori baru dari template benar-benar terbentuk.
 *
 * Berkasnya dibuat sungguhan lalu dibaca kembali lewat endpoint — bukan
 * array yang dioper langsung ke Action, karena yang paling mungkin salah
 * justru bagian bacanya.
 */
class ImportCatalogTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, string>|null  $header
     */
    private function xlsx(array $rows, ?array $header = null): UploadedFile
    {
        $header ??= ['nama', 'kategori', 'deskripsi', 'harga', 'durasi_menit', 'status'];

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([$header, ...$rows], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'impor').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'katalog.xlsx', null, null, true);
    }

    private function importServices(UploadedFile $file)
    {
        return $this->post($this->tenantUrl('services/import'), ['file' => $file]);
    }

    public function test_new_services_are_created_with_their_categories(): void
    {
        $this->actingAsClinicUser();

        $response = $this->importServices($this->xlsx([
            ['Facial Basic', 'Facial & Peeling', 'Bersih menyeluruh', '150000', '45', 'active'],
            ['Glow Booster', 'Skinbooster', '', '500000', '60', 'active'],
        ]))->assertOk();

        $this->assertSame(2, $response->json('data.imported'));
        $this->assertSame(0, $response->json('data.updated'));
        $this->assertSame([], $response->json('data.errors'));

        $facial = Service::query()->where('name', 'Facial Basic')->first();
        $this->assertNotNull($facial);
        $this->assertSame('Facial & Peeling', $facial->assignedCategory()?->name);
        $this->assertSame(45, $facial->duration_minutes);
    }

    /** Kategori yang belum ada dibuat saat impor, bukan ditolak. */
    public function test_a_category_named_in_the_file_is_created(): void
    {
        $this->actingAsClinicUser();

        $this->assertSame(0, Category::query()->count());

        $this->importServices($this->xlsx([
            ['Terapi Baru', 'Kategori Yang Belum Ada', '', '100000', '30', 'active'],
        ]))->assertOk();

        $category = Category::query()->first();

        $this->assertSame('Kategori Yang Belum Ada', $category?->name);
        $this->assertSame(CategorizableType::Service, $category?->type);
    }

    /** Nama yang sama dalam satu berkas hanya melahirkan satu kategori. */
    public function test_a_repeated_category_name_creates_one_row(): void
    {
        $this->actingAsClinicUser();

        $this->importServices($this->xlsx([
            ['Layanan A', 'Skinbooster', '', '100000', '30', 'active'],
            ['Layanan B', 'Skinbooster', '', '200000', '30', 'active'],
            ['Layanan C', 'Skinbooster', '', '300000', '30', 'active'],
        ]))->assertOk();

        $this->assertSame(1, Category::query()->where('name', 'Skinbooster')->count());
    }

    /** Nama yang sudah ada diperbarui, bukan digandakan. */
    public function test_an_existing_name_is_updated_not_duplicated(): void
    {
        $this->actingAsClinicUser();

        $lama = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial Basic',
            'price' => 100_000,
            'duration_minutes' => 30,
        ]);

        $response = $this->importServices($this->xlsx([
            ['Facial Basic', 'Facial & Peeling', '', '175000', '50', 'active'],
        ]))->assertOk();

        $this->assertSame(0, $response->json('data.imported'));
        $this->assertSame(1, $response->json('data.updated'));

        $this->assertSame(1, Service::query()->where('name', 'Facial Basic')->count());
        $this->assertSame(50, $lama->refresh()->duration_minutes);
        $this->assertSame('Facial & Peeling', $lama->assignedCategory()?->name);
    }

    /**
     * Inti fiturnya: pengisi template biasanya keliru di beberapa baris saja.
     * Menolak seluruh berkas berarti ia harus mengulang semuanya.
     */
    public function test_a_bad_row_does_not_take_the_good_ones_with_it(): void
    {
        $this->actingAsClinicUser();

        $response = $this->importServices($this->xlsx([
            ['Layanan Bagus', 'Skinbooster', '', '100000', '30', 'active'],
            ['', 'Skinbooster', '', '200000', '30', 'active'],
            ['Durasi Aneh', 'Skinbooster', '', '300000', 'bukan angka', 'active'],
            ['Layanan Bagus Kedua', 'Skinbooster', '', '400000', '60', 'active'],
        ]))->assertOk();

        $this->assertSame(2, $response->json('data.imported'));
        $this->assertCount(2, $response->json('data.errors'));

        // Nomor barisnya mengikuti spreadsheet, bukan indeks internal.
        $this->assertSame(3, $response->json('data.errors.0.row'));
        $this->assertSame('nama', $response->json('data.errors.0.field'));
        $this->assertSame(4, $response->json('data.errors.1.row'));

        $this->assertNotNull(Service::query()->where('name', 'Layanan Bagus Kedua')->first());
    }

    public function test_blank_rows_are_skipped_without_being_counted_as_errors(): void
    {
        $this->actingAsClinicUser();

        $response = $this->importServices($this->xlsx([
            ['Layanan A', 'Skinbooster', '', '100000', '30', 'active'],
            ['', '', '', '', '', ''],
            ['Layanan B', 'Skinbooster', '', '200000', '30', 'active'],
        ]))->assertOk();

        $this->assertSame(2, $response->json('data.imported'));
        $this->assertSame([], $response->json('data.errors'));
    }

    public function test_a_row_without_a_category_leaves_the_service_uncategorised(): void
    {
        $this->actingAsClinicUser();

        $this->importServices($this->xlsx([
            ['Tanpa Kategori', '', '', '100000', '30', 'active'],
        ]))->assertOk();

        $this->assertNull(
            Service::query()->where('name', 'Tanpa Kategori')->first()?->assignedCategory(),
        );
        $this->assertSame(0, Category::query()->count());
    }

    /** Header yang salah berarti seluruh isinya tidak bisa dipercaya. */
    public function test_a_wrong_header_is_refused_before_anything_is_written(): void
    {
        $this->actingAsClinicUser();

        $this->importServices($this->xlsx(
            [['Facial Basic', '150000']],
            ['nama_layanan', 'harga'],
        ))->assertStatus(422);

        $this->assertSame(0, Service::query()->count());
    }

    public function test_a_file_without_data_rows_is_refused(): void
    {
        $this->actingAsClinicUser();

        $this->importServices($this->xlsx([]))->assertStatus(422);
    }

    public function test_more_rows_than_the_limit_are_refused(): void
    {
        $this->actingAsClinicUser();

        $rows = [];

        foreach (range(1, ParseImportFileAction::MAX_ROWS + 1) as $index) {
            $rows[] = ['Layanan '.$index, 'Skinbooster', '', '100000', '30', 'active'];
        }

        $this->importServices($this->xlsx($rows))->assertStatus(422);

        $this->assertSame(0, Service::query()->count());
    }

    public function test_a_non_xlsx_upload_is_refused(): void
    {
        $this->actingAsClinicUser();

        $this->importServices(UploadedFile::fake()->create('katalog.csv', 10))
            ->assertStatus(422);
    }

    public function test_a_cashier_cannot_import(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->importServices($this->xlsx([
            ['Layanan A', 'Skinbooster', '', '100000', '30', 'active'],
        ]))->assertForbidden();

        $this->assertSame(0, Service::query()->count());
    }

    public function test_the_import_is_written_to_the_audit_log(): void
    {
        $this->actingAsClinicUser();

        $this->importServices($this->xlsx([
            ['Layanan A', 'Skinbooster', '', '100000', '30', 'active'],
        ]))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'service.import']);
    }

    // ---------------------------------------------------------------- produk

    public function test_products_are_imported_with_their_own_columns(): void
    {
        $this->actingAsClinicUser();

        $file = $this->xlsx(
            [['Serum Vitamin C', 'Serum', 'botol', '3', '150000', 'active']],
            ['nama', 'kategori', 'satuan', 'stok_minimum', 'harga', 'status'],
        );

        $this->post($this->tenantUrl('products/import'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $product = Product::query()->where('name', 'Serum Vitamin C')->first();

        $this->assertSame('Serum', $product?->assignedCategory()?->name);
        $this->assertSame('botol', $product?->unit);
        // Stok hanya tumbuh lewat mutasi; impor tidak boleh menyentuhnya.
        $this->assertSame(0, $product?->stock_balance);
    }

    // -------------------------------------------------------------- template

    public function test_the_template_lists_this_clinics_categories(): void
    {
        $this->actingAsClinicUser();

        Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Skinbooster',
            'type' => CategorizableType::Service,
            'status' => 'active',
        ]);

        $response = $this->get($this->tenantUrl('services/import/template'))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'template').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $spreadsheet = IOFactory::load($path);

        $this->assertSame('Template', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame('nama', $spreadsheet->getSheet(0)->getCell('A1')->getValue());

        $categories = $spreadsheet->getSheetByName('Kategori');
        $this->assertNotNull($categories);
        $this->assertSame('Skinbooster', $categories->getCell('A2')->getValue());
    }

    /**
     * Lembar isian hanya berisi header. Contoh yang duduk di sana akan ikut
     * masuk sebagai layanan sungguhan bagi siapa pun yang menambahkan
     * barisnya di bawah contoh tanpa menghapusnya lebih dulu.
     */
    public function test_the_template_sheet_carries_no_sample_data(): void
    {
        $this->actingAsClinicUser();

        $response = $this->get($this->tenantUrl('services/import/template'))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'template').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $spreadsheet = IOFactory::load($path);

        $this->assertSame(1, $spreadsheet->getSheet(0)->getHighestDataRow());

        // Contohnya tetap ada, di lembar terpisah.
        $examples = $spreadsheet->getSheetByName('Contoh Pengisian');
        $this->assertNotNull($examples);
        $this->assertSame('Facial Basic', $examples->getCell('A2')->getValue());
    }

    /**
     * Template yang diunduh harus bisa langsung diisi dan dikembalikan.
     * Kalau header-nya tidak sinkron dengan yang diharapkan pembaca, seluruh
     * fitur ini tidak akan pernah berhasil sekalipun tiap bagiannya benar.
     */
    public function test_the_downloaded_template_is_accepted_back_by_the_importer(): void
    {
        $this->actingAsClinicUser();

        $response = $this->get($this->tenantUrl('services/import/template'))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'template').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);

        // Pengisi template tinggal menambah baris di bawah header — tanpa
        // perlu menghapus apa pun lebih dulu.
        $sheet->fromArray(
            [['Layanan Dari Template', 'Skinbooster', '', '250000', '45', 'active']],
            null,
            'A2',
        );

        $filled = tempnam(sys_get_temp_dir(), 'isian').'.xlsx';
        (new Xlsx($spreadsheet))->save($filled);

        $this->importServices(new UploadedFile($filled, 'isian.xlsx', null, null, true))
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.errors', []);

        $this->assertNotNull(Service::query()->where('name', 'Layanan Dari Template')->first());
    }
}
