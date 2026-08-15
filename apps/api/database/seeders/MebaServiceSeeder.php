<?php

namespace Database\Seeders;

use App\Enums\ServiceCategory;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Katalog treatment final MEBA Clinic — 30 layanan, empat kategori.
 *
 * Idempotent: dijalankan berkali-kali menghasilkan keadaan yang sama. Layanan
 * dicocokkan berdasarkan nama; yang sudah ada diperbarui harga dan
 * kategorinya, bukan diduplikasi.
 *
 * Layanan di luar daftar ini dibereskan dengan cara yang aman terhadap
 * riwayat: yang belum pernah dipakai booking, nota, rekam tindakan, etalase,
 * pengingat, atau promo akan dihapus; yang sudah terpakai hanya diarsipkan.
 * Menghapus paksa layanan yang sudah masuk nota berarti merusak riwayat
 * transaksi — dan foreign key-nya memang menolak (RESTRICT).
 *
 * Durasi, harga modal, dan komisi tambahan sengaja TIDAK diisi: pricelist
 * hanya memuat harga jual. Kolom duration_minutes wajib di database, jadi
 * layanan baru memakai default kolomnya (30 menit) dan bisa disesuaikan
 * operator lewat form; layanan yang durasinya sudah diatur tidak ditimpa.
 *
 * Fee terapis Rp5.000 per pasien bukan bagian dari harga treatment — angkanya
 * dihitung terpisah oleh CommissionCalculator berdasarkan kunjungan yang
 * benar-benar memuat tindakan.
 */
class MebaServiceSeeder extends Seeder
{
    /** Tenant pemilik katalog ini. */
    private const TENANT_SLUG = 'demo';

    /**
     * @var array<int, array{name: string, category: ServiceCategory, price: int}>
     */
    private const CATALOG = [
        // Skinbooster
        ['name' => 'Skinbooster Collagen', 'category' => ServiceCategory::Skinbooster, 'price' => 3_000_000],
        ['name' => 'Skinbooster Pink Glow', 'category' => ServiceCategory::Skinbooster, 'price' => 800_000],
        ['name' => 'Skinbooster DNA Salmon Korea', 'category' => ServiceCategory::Skinbooster, 'price' => 800_000],
        ['name' => 'Skinbooster DNA Salmon Eropa', 'category' => ServiceCategory::Skinbooster, 'price' => 1_100_000],
        ['name' => 'Skinbooster Flek Eropa', 'category' => ServiceCategory::Skinbooster, 'price' => 1_100_000],
        ['name' => 'Skinbooster Acne Killer', 'category' => ServiceCategory::Skinbooster, 'price' => 750_000],
        ['name' => 'Skinbooster Microbotox', 'category' => ServiceCategory::Skinbooster, 'price' => 1_500_000],
        ['name' => 'Skinbooster HA Staris', 'category' => ServiceCategory::Skinbooster, 'price' => 850_000],
        ['name' => 'Skinbooster Hydra PLLA', 'category' => ServiceCategory::Skinbooster, 'price' => 1_300_000],
        ['name' => 'Skinbooster Exoxome', 'category' => ServiceCategory::Skinbooster, 'price' => 2_500_000],
        ['name' => 'Skinbooster Vitaran', 'category' => ServiceCategory::Skinbooster, 'price' => 1_800_000],
        ['name' => 'Skinbooster Cellbooster', 'category' => ServiceCategory::Skinbooster, 'price' => 3_500_000],
        ['name' => 'Skinbooster Shine & Glow', 'category' => ServiceCategory::Skinbooster, 'price' => 2_800_000],

        // Derma Treatment
        ['name' => 'Derma Rejuvenation', 'category' => ServiceCategory::DermaTreatment, 'price' => 550_000],
        ['name' => 'Derma Pink Glow', 'category' => ServiceCategory::DermaTreatment, 'price' => 500_000],
        ['name' => 'Derma DNA Salmon Korea', 'category' => ServiceCategory::DermaTreatment, 'price' => 650_000],
        ['name' => 'Derma Flek Eropa', 'category' => ServiceCategory::DermaTreatment, 'price' => 850_000],
        ['name' => 'Derma Porzelan', 'category' => ServiceCategory::DermaTreatment, 'price' => 1_400_000],

        // Facial & Peeling
        ['name' => 'Facial Peeling Glow', 'category' => ServiceCategory::FacialPeeling, 'price' => 500_000],
        ['name' => 'Facial Peeling Lactopeel', 'category' => ServiceCategory::FacialPeeling, 'price' => 500_000],
        ['name' => 'Facial Peeling Retinol', 'category' => ServiceCategory::FacialPeeling, 'price' => 500_000],
        ['name' => 'Facial CO2 Carboxi', 'category' => ServiceCategory::FacialPeeling, 'price' => 650_000],
        ['name' => 'Facial Basic', 'category' => ServiceCategory::FacialPeeling, 'price' => 100_000],
        ['name' => 'Mikrodermabrasi', 'category' => ServiceCategory::FacialPeeling, 'price' => 85_000],
        ['name' => 'Oxi Infusion', 'category' => ServiceCategory::FacialPeeling, 'price' => 85_000],
        ['name' => 'PDT', 'category' => ServiceCategory::FacialPeeling, 'price' => 80_000],

        // Treatment Lainnya
        ['name' => 'Botox Full Face', 'category' => ServiceCategory::Other, 'price' => 2_500_000],
        ['name' => 'Slimming Treatment', 'category' => ServiceCategory::Other, 'price' => 3_500_000],
        ['name' => 'Meso Chubu / Double Chin', 'category' => ServiceCategory::Other, 'price' => 450_000],
        ['name' => 'Biopen EMS Treatment', 'category' => ServiceCategory::Other, 'price' => 750_000],
    ];

    /**
     * Katalog ini milik satu klinik, bukan data bawaan platform. Menjalankan
     * seeder tanpa argumen hanya menyentuh tenant kliniknya; tenant lain punya
     * katalog sendiri dan tidak boleh kemasukan daftar treatment orang lain.
     */
    public function run(): void
    {
        $slug = self::TENANT_SLUG;

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->command?->warn("Tenant '{$slug}' tidak ditemukan; katalog layanan dilewati.");

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

        $this->retireNonCatalogServices();

        foreach (self::CATALOG as $item) {
            $service = Service::query()->firstOrNew(['name' => $item['name']]);

            $service->fill([
                'category' => $item['category'],
                'price' => $item['price'],
                'status' => 'active',
            ]);

            $service->save();
        }
    }

    /**
     * Bereskan layanan di luar katalog final.
     */
    private function retireNonCatalogServices(): void
    {
        $keep = array_column(self::CATALOG, 'name');

        $others = Service::query()->whereNotIn('name', $keep)->get();

        foreach ($others as $service) {
            if ($this->isReferenced($service)) {
                // Riwayat tindakan tetap utuh; layanannya hilang dari katalog
                // aktif karena daftar default hanya menampilkan yang aktif.
                $service->update(['status' => 'archived']);

                continue;
            }

            $service->delete();
        }
    }

    /**
     * Tabel yang menyimpan jejak sebuah layanan. Promo memakai relasi morph
     * tanpa foreign key, jadi ikut diperiksa manual — menghapus layanannya
     * akan meninggalkan promo yang menunjuk ke ruang kosong.
     */
    private function isReferenced(Service $service): bool
    {
        $tables = [
            'bookings',
            'transaction_items',
            'treatment_records',
            'company_treatments',
            'reminder_rules',
        ];

        foreach ($tables as $table) {
            if (DB::table($table)->where('service_id', $service->id)->exists()) {
                return true;
            }
        }

        return DB::table('promo_items')
            ->where('promotable_type', Service::class)
            ->where('promotable_id', $service->id)
            ->exists();
    }
}
