<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stok yang habis dipakai klinik sendiri butuh jenisnya sendiri.
 *
 * Sebelumnya semua barang yang keluar tanpa terjual masuk satu keranjang
 * "Stok Keluar", jadi masker yang habis dipakai terapis tidak terpisah dari
 * botol yang pecah. Padahal keduanya berbeda arti: yang terpakai treatment
 * adalah biaya layanan, sedangkan yang rusak adalah kerugian — dan klinik
 * tidak bisa menghitung biaya bahan per periode kalau keduanya menyatu.
 *
 * Mengikuti cara yang sama seperti penambahan status pembayaran cicil:
 * PostgreSQL memakai CHECK constraint yang diganti isinya, SQLite tidak bisa
 * mengubah CHECK tanpa rebuild tabel sehingga kolomnya dijadikan string biasa
 * — enum PHP tetap menjaga nilainya di sana.
 */
return new class extends Migration
{
    private const TYPES = ['in', 'out_manual', 'used_internal', 'sold_pos', 'rollback'];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->relaxSqliteColumn();

            return;
        }

        $this->replaceCheck(self::TYPES);
    }

    public function down(): void
    {
        // Yang sudah tercatat sebagai pemakaian sendiri dikembalikan ke stok
        // keluar biasa, bukan dihapus: barangnya memang sudah keluar, dan
        // menghilangkan barisnya membuat saldo tidak lagi cocok.
        DB::table('stock_movements')
            ->where('type', 'used_internal')
            ->update(['type' => 'out_manual']);

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $this->replaceCheck(['in', 'out_manual', 'sold_pos', 'rollback']);
    }

    /**
     * @param  array<int, string>  $types
     */
    private function replaceCheck(array $types): void
    {
        $allowed = collect($types)
            ->map(fn (string $type): string => "'".$type."'::character varying")
            ->implode(', ');

        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check CHECK (type::text = ANY (ARRAY[{$allowed}]::text[]))");
    }

    private function relaxSqliteColumn(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->string('type')->change();
        });
    }
};
