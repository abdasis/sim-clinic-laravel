<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tingkat keanggotaan pasien, berikut potongan yang menyertainya.
 *
 * Dibuat sebagai master tersendiri, bukan kolom potongan di pasien: klinik
 * mengubah besaran manfaatnya sesekali ("member Gold naik jadi 12%"), dan
 * kalau angkanya menempel di tiap pasien, perubahan itu berarti menyunting
 * ratusan baris satu per satu — dan yang terlewat diam-diam memakai angka lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('discount_type');
            $table->decimal('discount_value', 8, 2);
            /**
             * Boleh menumpuk dengan promo?
             *
             * Bawaannya tidak. Di kebanyakan klinik potongan member dan promo
             * tidak digabung ("tidak berlaku kelipatan"), dan menumpuk secara
             * diam-diam berarti margin yang hilang tanpa ada yang memutuskan.
             * Klinik yang memang ingin menumpuk tinggal menyalakannya.
             */
            $table->boolean('stacks_with_promo')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            // Nama tingkat dibaca kasir dan pasien; kembar cuma bikin ragu.
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_tiers');
    }
};
