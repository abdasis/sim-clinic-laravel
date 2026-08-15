<?php

namespace Database\Seeders;

use App\Enums\ProductCategory;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Katalog produk retail final MEBA Clinic — 20 produk, lima kategori.
 *
 * Idempotent: dijalankan berkali-kali menghasilkan keadaan yang sama. Produk
 * dicocokkan berdasarkan nama; yang sudah ada diperbarui harga dan
 * kategorinya, bukan diduplikasi.
 *
 * Produk di luar daftar ini dibereskan dengan cara yang aman terhadap
 * riwayat: yang belum pernah dipakai transaksi/mutasi stok dihapus, yang
 * sudah terpakai hanya diarsipkan. Menghapus paksa produk yang sudah masuk
 * nota berarti merusak riwayat penjualan — dan foreign key-nya memang
 * menolak (RESTRICT).
 *
 * Stok awal, ambang minimum, harga beli, dan supplier sengaja TIDAK diisi:
 * tidak ada sumber datanya, dan angka karangan di master stok lebih berbahaya
 * daripada kolom kosong. Stok terisi lewat modul Inventaris saat barang
 * benar-benar masuk.
 */
class MebaProductSeeder extends Seeder
{
    /** Satuan netral yang sudah dipakai project untuk produk retail. */
    private const UNIT = 'pcs';

    /** Tenant pemilik katalog ini. */
    private const TENANT_SLUG = 'demo';

    /**
     * @var array<int, array{name: string, category: ProductCategory, price: int}>
     */
    private const CATALOG = [
        // Facial Wash
        ['name' => 'Radiant Face Wash', 'category' => ProductCategory::FacialWash, 'price' => 75_000],
        ['name' => 'Acne Skin Face Wash', 'category' => ProductCategory::FacialWash, 'price' => 75_000],
        ['name' => 'Creamy Facial Foam', 'category' => ProductCategory::FacialWash, 'price' => 65_000],

        // Toner
        ['name' => 'Purifying Oily Skin Toner', 'category' => ProductCategory::Toner, 'price' => 65_000],
        ['name' => 'Cleansing Toner', 'category' => ProductCategory::Toner, 'price' => 65_000],

        // Sunscreen
        ['name' => 'Niacisund Tinted', 'category' => ProductCategory::Sunscreen, 'price' => 95_000],
        ['name' => 'UV Defense Cream', 'category' => ProductCategory::Sunscreen, 'price' => 85_000],
        ['name' => 'Sunscreen Cream Acne', 'category' => ProductCategory::Sunscreen, 'price' => 85_000],
        ['name' => 'UV Skin Tint Neutral', 'category' => ProductCategory::Sunscreen, 'price' => 200_000],

        // Serum
        ['name' => 'Serum Vita B Advance Radiant Gel', 'category' => ProductCategory::Serum, 'price' => 150_000],
        ['name' => 'Serum Vitalizing C10', 'category' => ProductCategory::Serum, 'price' => 150_000],
        ['name' => 'Serum Acne Probiotic', 'category' => ProductCategory::Serum, 'price' => 185_000],
        ['name' => 'Serum Skintastic Lightening Serum', 'category' => ProductCategory::Serum, 'price' => 350_000],
        ['name' => 'Acne Clarify Serum', 'category' => ProductCategory::Serum, 'price' => 185_000],
        ['name' => 'Acnemies Spot', 'category' => ProductCategory::Serum, 'price' => 95_000],

        // Night Cream
        ['name' => 'Cystearin Cream', 'category' => ProductCategory::NightCream, 'price' => 185_000],
        ['name' => 'CyteaPro', 'category' => ProductCategory::NightCream, 'price' => 350_000],
        ['name' => 'Skin Revitalizing Cream', 'category' => ProductCategory::NightCream, 'price' => 120_000],
        ['name' => 'Vita B Gel', 'category' => ProductCategory::NightCream, 'price' => 70_000],
        ['name' => 'Night Cream Whitening Acne', 'category' => ProductCategory::NightCream, 'price' => 120_000],
    ];

    /**
     * Katalog ini milik satu klinik, bukan data bawaan platform. Menjalankan
     * seeder tanpa argumen hanya menyentuh tenant kliniknya; tenant lain punya
     * katalog sendiri dan tidak boleh kemasukan daftar produk orang lain.
     */
    public function run(): void
    {
        $slug = self::TENANT_SLUG;

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->command?->warn("Tenant '{$slug}' tidak ditemukan; katalog produk dilewati.");

            return;
        }

        $this->seedTenant($tenant);

        app()->forgetInstance('tenant');
    }

    public function seedTenant(Tenant $tenant): void
    {
        // Seeder berjalan di CLI tanpa middleware tenant; ikat manual supaya
        // BelongsToTenant dan TenantScope bekerja seperti saat request.
        app()->instance('tenant', $tenant);

        $this->retireNonCatalogProducts();

        foreach (self::CATALOG as $item) {
            $product = Product::query()->firstOrNew(['name' => $item['name']]);

            $product->fill([
                'category' => $item['category'],
                'price' => $item['price'],
                'status' => 'active',
            ]);

            // Satuan hanya diisi saat produk baru; yang sudah disesuaikan
            // operator (mis. "tube") tidak ditimpa kembali ke default.
            if (! $product->exists) {
                $product->unit = self::UNIT;
            }

            $product->save();
        }
    }

    /**
     * Bereskan produk di luar katalog final.
     */
    private function retireNonCatalogProducts(): void
    {
        $keep = array_column(self::CATALOG, 'name');

        $others = Product::query()->whereNotIn('name', $keep)->get();

        foreach ($others as $product) {
            if ($this->isReferenced($product)) {
                // Riwayat penjualan tetap utuh; produknya hilang dari master
                // aktif karena katalog default hanya menampilkan yang aktif.
                $product->update(['status' => 'archived']);

                continue;
            }

            $product->delete();
        }
    }

    /**
     * Jejak sebuah produk. Promo memakai relasi morph tanpa foreign key, jadi
     * ikut diperiksa manual — menghapus produknya tidak ditolak database, tapi
     * meninggalkan promo yang menunjuk ke ruang kosong.
     */
    private function isReferenced(Product $product): bool
    {
        return DB::table('transaction_items')->where('product_id', $product->id)->exists()
            || DB::table('stock_movements')->where('product_id', $product->id)->exists()
            || DB::table('promo_items')
                ->where('promotable_type', Product::class)
                ->where('promotable_id', $product->id)
                ->exists();
    }
}
