<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Katalog layanan dan produk terurut abjad, bukan dari yang terbaru.
 *
 * Urutan bawaannya dulu waktu pembuatan, padahal katalog selalu dibaca untuk
 * mencari satu nama tertentu — di layar kasir maupun di layar master. Layanan
 * yang ditambahkan tahun lalu terkubur di halaman terakhir tanpa alasan yang
 * bisa ditebak pembacanya.
 */
class CatalogAlphabeticalOrderTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /** Ditulis acak, dengan besar-kecil huruf campur seperti isian nyata. */
    private const NAMES = ['Totok Wajah', 'peeling', 'Facial Glow', 'acne treatment'];

    private const SORTED = ['acne treatment', 'Facial Glow', 'peeling', 'Totok Wajah'];

    private function makeServices(): void
    {
        foreach (self::NAMES as $name) {
            Service::create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
                'price' => 100000,
                'duration_minutes' => 60,
                'status' => 'active',
            ]);
        }
    }

    private function makeProducts(): void
    {
        foreach (self::NAMES as $name) {
            Product::factory()->create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
                'status' => 'active',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, string>
     */
    private function names(string $path, array $query = []): array
    {
        return collect(
            $this->getJson($this->tenantUrl($path).'?'.http_build_query($query))
                ->assertOk()
                ->json('data'),
        )->pluck('name')->all();
    }

    public function test_services_come_back_in_alphabetical_order(): void
    {
        $this->actingAsClinicUser();
        $this->makeServices();

        $this->assertSame(self::SORTED, $this->names('services'));
    }

    public function test_products_come_back_in_alphabetical_order(): void
    {
        $this->actingAsClinicUser();
        $this->makeProducts();

        $this->assertSame(self::SORTED, $this->names('products'));
    }

    /**
     * Penjagaan yang sebenarnya: besar-kecil huruf tidak boleh membelah
     * daftarnya jadi dua kelompok. Pada collation biner, semua nama berhuruf
     * kapital menyusul lebih dulu dan "peeling" terlempar ke bawah "Totok".
     */
    public function test_lowercase_names_are_not_pushed_to_the_bottom(): void
    {
        $this->actingAsClinicUser();
        $this->makeServices();

        $names = $this->names('services');

        $this->assertSame('acne treatment', $names[0]);
        $this->assertSame('peeling', $names[2]);
    }

    /**
     * Urutan pilihan pengguna tetap menang, dan sama tidak peduli besar-kecil
     * huruf: menekan kepala kolom Nama dulu memberi urutan biner yang justru
     * memisahkan nama berhuruf kecil ke satu ujung sendiri.
     */
    public function test_an_explicit_descending_sort_is_also_case_insensitive(): void
    {
        $this->actingAsClinicUser();
        $this->makeServices();

        $this->assertSame(
            array_reverse(self::SORTED),
            $this->names('services', ['sort' => 'name', 'direction' => 'desc']),
        );
    }

    /** Mengurut kolom lain tidak ikut terbawa aturan huruf kecil. */
    public function test_sorting_by_another_column_still_works(): void
    {
        $this->actingAsClinicUser();
        $this->makeServices();

        $this->assertCount(
            4,
            $this->names('services', ['sort' => 'price', 'direction' => 'asc']),
        );
    }

    /** Katalog kasir memakai endpoint yang sama, jadi ikut terurut. */
    public function test_the_cashier_catalog_is_ordered_too(): void
    {
        $this->actingAsClinicUser();
        $this->makeProducts();

        $names = $this->names('products', ['per_page' => 100, 'filter' => ['type' => 'retail']]);

        $this->assertSame(
            collect($names)->sortBy(fn (string $name) => mb_strtolower($name))->values()->all(),
            $names,
        );
    }
}
