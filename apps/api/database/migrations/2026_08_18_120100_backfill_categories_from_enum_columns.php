<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pindahkan kategori enum lama menjadi baris `categories` milik tiap klinik,
 * lalu tempelkan ke itemnya lewat pivot.
 *
 * Kolom `category` lama sengaja TIDAK di-drop di sini: selama belum terbukti
 * benar di produksi, rollback harus bisa mengembalikan keadaan semula tanpa
 * kehilangan data. Drop-nya migrasi terpisah nanti.
 *
 * Label ditulis apa adanya di sini, bukan diambil dari enum atau berkas lang.
 * Migrasi adalah catatan sejarah — hasilnya tidak boleh berubah hanya karena
 * suatu saat enum-nya dihapus atau terjemahannya disunting.
 */
return new class extends Migration
{
    private const LABELS = [
        'service' => [
            'skinbooster' => 'Skinbooster',
            'derma_treatment' => 'Derma Treatment',
            'facial_peeling' => 'Facial & Peeling',
            'other' => 'Treatment Lainnya',
        ],
        'product' => [
            'facial_wash' => 'Facial Wash',
            'toner' => 'Toner',
            'sunscreen' => 'Sunscreen',
            'serum' => 'Serum',
            'night_cream' => 'Night Cream',
        ],
        'expense' => [
            'operational' => 'Operasional Klinik',
            'salary' => 'Gaji',
            'incentive' => 'Fee & Insentif',
            'purchase' => 'Pembelian Barang',
            'rent' => 'Sewa',
            'utility' => 'Listrik, Air, Internet',
            'marketing' => 'Promosi & Pemasaran',
            'other' => 'Lain-lain',
        ],
    ];

    private const TABLES = [
        'service' => 'services',
        'product' => 'products',
        'expense' => 'expenses',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::TABLES as $type => $table) {
            $this->backfill($type, $table, $now);
        }

        // `expenses.category` wajib isi, sementara kolomnya sudah tidak
        // ditulis lagi. Dibiarkan begitu, setiap pengeluaran baru gagal
        // disimpan. Dilonggarkan, bukan di-drop: nilai lamanya masih
        // dibutuhkan kalau migrasi ini harus dibatalkan.
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('category')->nullable()->change();
        });
    }

    private function backfill(string $type, string $table, mixed $now): void
    {
        $pairs = DB::table($table)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->select('tenant_id', 'category')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $name = self::LABELS[$type][$pair->category] ?? $this->humanise($pair->category);

            $categoryId = DB::table('categories')
                ->where('tenant_id', $pair->tenant_id)
                ->where('type', $type)
                ->where('name', $name)
                ->value('id')
                ?? DB::table('categories')->insertGetId([
                    'tenant_id' => $pair->tenant_id,
                    'name' => $name,
                    'type' => $type,
                    'description' => null,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            $this->attach($table, $type, $pair, $categoryId);
        }
    }

    private function attach(string $table, string $type, object $pair, int $categoryId): void
    {
        DB::table($table)
            ->where('tenant_id', $pair->tenant_id)
            ->where('category', $pair->category)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($type, $categoryId): void {
                $insert = [];

                foreach ($rows as $row) {
                    $insert[] = [
                        'category_id' => $categoryId,
                        // Alias morph, bukan nama kelas penuh — sejalan dengan
                        // peta morph aplikasi.
                        'categorizable_type' => $type,
                        'categorizable_id' => $row->id,
                    ];
                }

                if ($insert !== []) {
                    DB::table('categorizables')->insertOrIgnore($insert);
                }
            });
    }

    /** Nilai enum tak dikenal tetap terbawa, bukan hilang diam-diam. */
    private function humanise(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    public function down(): void
    {
        // Pengeluaran yang dibuat setelah migrasi ini tidak punya nilai enum
        // lama. Diisi 'other' supaya kolomnya bisa kembali wajib tanpa
        // menggagalkan rollback — kategori sebenarnya tetap ada di pivot
        // sampai baris di bawah menghapusnya.
        DB::table('expenses')->whereNull('category')->update(['category' => 'other']);

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('category')->nullable(false)->change();
        });

        // Kolom enum lama tidak pernah disentuh isinya, jadi cukup kosongkan
        // yang dibuat migrasi ini.
        DB::table('categorizables')->delete();
        DB::table('categories')->delete();
    }
};
