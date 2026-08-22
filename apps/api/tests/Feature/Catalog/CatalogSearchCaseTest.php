<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pencarian katalog tidak peduli besar-kecil huruf.
 *
 * Nama layanan dan produk diketik apa adanya oleh staf — "Facial Glow",
 * "peeling", "BOTOX" — sementara yang mencari mengetik seingatnya. Pencarian
 * yang menuntut huruf persis sama membuat katalog terasa kosong padahal
 * barangnya ada.
 *
 * Tes ini baru bergigi lewat konfigurasi PostgreSQL (`phpunit.pgsql.xml`):
 * LIKE di PostgreSQL peka besar-kecil huruf, sementara di SQLite tidak. Itu
 * yang membuat cacatnya lolos dari suite bawaan sampai sekarang — di sini
 * lulus, di klinik yang sungguhan tidak ketemu.
 */
class CatalogSearchCaseTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /** Besar-kecil hurufnya campur, seperti isian nyata. */
    private const NAMES = ['Facial Glow', 'peeling', 'BOTOX', 'Totok Wajah'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();

        foreach (self::NAMES as $name) {
            Service::create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
                'price' => 100000,
                'duration_minutes' => 60,
                'status' => 'active',
            ]);

            Product::factory()->create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
                'status' => 'active',
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function search(string $path, string $keyword): array
    {
        return collect(
            $this->getJson($this->tenantUrl($path).'?'.http_build_query(['search' => $keyword]))
                ->assertOk()
                ->json('data'),
        )->pluck('name')->all();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function keywords(): array
    {
        return [
            'huruf kecil, data berkapital' => ['facial', 'Facial Glow'],
            'huruf besar, data berkapital' => ['FACIAL', 'Facial Glow'],
            'berkapital, data huruf kecil' => ['Peeling', 'peeling'],
            'huruf kecil, data huruf besar' => ['botox', 'BOTOX'],
            'campur di tengah kata' => ['tOtOk', 'Totok Wajah'],
        ];
    }

    #[DataProvider('keywords')]
    public function test_service_search_ignores_letter_case(string $keyword, string $expected): void
    {
        $this->assertSame([$expected], $this->search('services', $keyword));
    }

    #[DataProvider('keywords')]
    public function test_product_search_ignores_letter_case(string $keyword, string $expected): void
    {
        $this->assertSame([$expected], $this->search('products', $keyword));
    }

    /** Yang tidak ada tetap tidak ada — pencariannya melonggar, bukan jebol. */
    public function test_a_keyword_that_matches_nothing_still_returns_nothing(): void
    {
        $this->assertSame([], $this->search('services', 'mikrodermabrasi'));
        $this->assertSame([], $this->search('products', 'mikrodermabrasi'));
    }
}
