<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu kolom `therapist_id` di transaksi dipakai untuk dua hal sekaligus:
 * siapa yang mengerjakan, dan siapa yang dihitung target penjualannya.
 * Klinik memerlukan keduanya terpisah.
 *
 *  - Satu kunjungan bisa ditangani lebih dari satu orang. Facial yang butuh
 *    satu terapis dan satu dokter membuat keduanya berhak atas fee kunjungan
 *    masing-masing, dan satu kolom tidak bisa menampung dua nama.
 *  - Yang menawarkan produk atau layanan tambahan belum tentu yang
 *    mengerjakan, dan sering kali memang tidak ada — pasien membeli atas
 *    kemauan sendiri. Karena itu penawar melekat per baris jualan dan boleh
 *    kosong.
 *
 * Data lama dipindahkan dengan mempertahankan angkanya: terapis yang tercatat
 * menjadi pelaksana DAN penawar seluruh baris transaksi itu. Kalau penawarnya
 * dikosongkan, komisi persentase periode-periode lampau akan menyusut begitu
 * dihitung ulang — laporan yang sudah diserahkan berubah tanpa ada yang
 * mengubah kebijakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_performers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            // Restrict: staf yang pernah mengerjakan kunjungan tidak boleh
            // lenyap dari riwayat fee hanya karena akunnya dihapus.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['transaction_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            // Null berarti tidak ada yang menawarkan — pasien membeli sendiri.
            $table->foreignId('offered_by')->nullable()->after('service_id')
                ->constrained('users')->restrictOnDelete();

            $table->index(['tenant_id', 'offered_by']);
        });

        $this->carryOverExistingAttribution();

        // Foreign key-nya dilepas lebih dulu di kedua driver. SQLite
        // membangun ulang tabelnya saat kolom dibuang, dan definisi foreign
        // key yang tertinggal membuat pembangunan itu gagal.
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['therapist_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('therapist_id');
        });
    }

    private function carryOverExistingAttribution(): void
    {
        $now = now();

        DB::table('transactions')
            ->whereNotNull('therapist_id')
            ->orderBy('id')
            ->chunkById(500, function ($transactions) use ($now) {
                $rows = [];

                foreach ($transactions as $transaction) {
                    $rows[] = [
                        'tenant_id' => $transaction->tenant_id,
                        'transaction_id' => $transaction->id,
                        'user_id' => $transaction->therapist_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    DB::table('transaction_items')
                        ->where('transaction_id', $transaction->id)
                        ->update(['offered_by' => $transaction->therapist_id]);
                }

                DB::table('transaction_performers')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('therapist_id')->nullable()->after('cashier_id')
                ->constrained('users')->restrictOnDelete();
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['offered_by']);
            }

            $table->dropColumn('offered_by');
        });

        Schema::dropIfExists('transaction_performers');
    }
};
