<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Database yang tertinggal migrasi dulu menjawab 500 telanjang. Di layar itu
 * tampak sebagai daftar kosong — sementara kartu statistik di halaman yang
 * sama tetap berisi angka, karena hanya menghitung baris. Yang terbaca
 * pengguna: "jumlahnya ada, datanya hilang".
 *
 * Test ini menjaga jawabannya tetap menyebut langkah yang harus diambil, dan
 * sengaja dijalankan di SQLite maupun PostgreSQL karena keduanya melaporkan
 * tabel hilang dengan kode yang berbeda.
 */
class SchemaOutdatedResponseTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_missing_table_is_explained_instead_of_crashing(): void
    {
        $this->actingAsClinicUser();
        // Pencarian promo baru berjalan kalau halamannya berisi baris.
        Product::factory()->create(['tenant_id' => $this->tenant->id]);

        // Tiru database yang belum menjalankan migrasi promo.
        Schema::drop('promo_items');
        Schema::drop('promos');

        $response = $this->getJson($this->tenantUrl('products'));

        $response->assertStatus(503);
        $response->assertJsonPath('message', __('general.schema_outdated'));
    }

    public function test_detail_sql_is_not_leaked_to_the_client(): void
    {
        $this->actingAsClinicUser();
        Product::factory()->create(['tenant_id' => $this->tenant->id]);

        Schema::drop('promo_items');
        Schema::drop('promos');

        $body = $this->getJson($this->tenantUrl('products'))->getContent();

        $this->assertStringNotContainsString('SQLSTATE', (string) $body);
        $this->assertStringNotContainsString('select *', (string) $body);
    }

    public function test_healthy_schema_still_serves_the_list(): void
    {
        $this->actingAsClinicUser();
        Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->getJson($this->tenantUrl('products'))->assertOk();
    }
}
