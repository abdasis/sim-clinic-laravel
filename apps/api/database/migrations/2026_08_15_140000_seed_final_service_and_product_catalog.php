<?php

use Database\Seeders\MebaProductSeeder;
use Database\Seeders\MebaServiceSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Pasang katalog final MEBA Clinic — 30 treatment dan 20 produk — sekali saja
 * per environment.
 *
 * Sengaja lewat migrasi, bukan langkah di skrip deploy: kedua seeder ini
 * memaksa katalog jadi persis daftar finalnya, sehingga layanan/produk di
 * luar daftar akan dihapus (bila belum pernah dipakai) atau diarsipkan (bila
 * sudah masuk riwayat). Itu benar untuk sekali pembersihan data percobaan,
 * tapi berbahaya bila terulang tiap deploy — produk yang nanti ditambahkan
 * staf lewat menu Master Data akan ikut hilang. Migrasi hanya jalan sekali
 * dan tercatat di tabel migrations, jadi tepat untuk keperluan ini.
 *
 * Saat database masih kosong (mis. migrate:fresh atau suite pengujian) belum
 * ada tenant sama sekali; seeder-nya mundur dengan tenang dan katalog dipasang
 * belakangan oleh DatabaseSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(MebaServiceSeeder::class)->run();
        app(MebaProductSeeder::class)->run();
    }

    public function down(): void
    {
        // Tidak bisa dibatalkan: data percobaan yang dibersihkan tidak punya
        // salinan untuk dikembalikan. Menghapus katalog final di sini justru
        // akan menjatuhkan kasir yang sudah memakainya.
    }
};
