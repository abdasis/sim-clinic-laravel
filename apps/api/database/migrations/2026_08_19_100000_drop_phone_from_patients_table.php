<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pasien cukup punya satu nomor: WhatsApp.
 *
 * Dua kolom nomor tidak pernah berguna. Yang dipakai mengirim pengingat dan
 * broadcast hanya WhatsApp, sementara kolom telepon cuma jadi tempat mengetik
 * nomor yang sama dua kali — dan karena telepon yang wajib diisi sedangkan
 * WhatsApp opsional, banyak pasien justru berakhir dengan kolom WhatsApp
 * kosong dan tidak pernah bisa dikirimi apa pun.
 *
 * Nomor telepon disalin lebih dulu ke kolom WhatsApp yang masih kosong, jadi
 * tidak ada pasien yang kehilangan nomornya. Sesudah itu WhatsApp mengambil
 * alih seluruh peran telepon: wajib diisi, dan terindeks karena itu yang
 * dipakai mencari pasien dan mendeteksi nomor ganda.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('patients')
            ->where(fn (Builder $query) => $query->whereNull('whatsapp')->orWhere('whatsapp', ''))
            ->update(['whatsapp' => DB::raw('phone')]);

        // Indeks lama harus lepas dulu: sebagian database menolak membuang
        // kolom yang masih dipakai indeks.
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'phone']);
        });

        Schema::table('patients', function (Blueprint $table): void {
            $table->string('whatsapp', 50)->nullable(false)->change();
            $table->index(['tenant_id', 'whatsapp']);
        });

        Schema::table('patients', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });
    }

    /**
     * Kolomnya kembali, isinya tidak sepenuhnya: nomor telepon yang dulu
     * berbeda dari nomor WhatsApp sudah tidak ada lagi jejaknya. Yang
     * dikembalikan adalah salinan nomor WhatsApp, dan kolomnya dibiarkan
     * boleh kosong karena tebakan itu tidak layak dijadikan data wajib.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->string('phone', 50)->nullable();
        });

        DB::table('patients')->update(['phone' => DB::raw('whatsapp')]);

        Schema::table('patients', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'whatsapp']);
            $table->string('whatsapp', 50)->nullable()->change();
            $table->index(['tenant_id', 'phone']);
        });
    }
};
