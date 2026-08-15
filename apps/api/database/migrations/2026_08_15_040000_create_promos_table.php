<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promo klinik: potongan harga yang berlaku pada layanan dan/atau produk
 * tertentu dalam rentang tanggal. Sasarannya morph supaya satu promo bisa
 * memuat campuran keduanya tanpa dua tabel pivot yang nyaris sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->enum('discount_type', ['percent', 'fixed']);
            $table->decimal('discount_value', 12, 2);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            // Kasir menanyakan "promo yang berlaku hari ini" pada tiap muat
            // katalog, jadi rentang dan statusnya diindeks bersama.
            $table->index(['tenant_id', 'status', 'starts_at', 'ends_at']);
        });

        Schema::create('promo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('promo_id')->constrained('promos')->cascadeOnDelete();
            $table->morphs('promotable');
            $table->timestamps();

            // Satu sasaran hanya boleh terdaftar sekali dalam satu promo.
            $table->unique(['promo_id', 'promotable_type', 'promotable_id'], 'promo_items_unique_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_items');
        Schema::dropIfExists('promos');
    }
};
