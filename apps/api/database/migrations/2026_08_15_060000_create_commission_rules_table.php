<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan fee terapis disimpan sebagai data, bukan ditanam di kode.
 *
 * Empat skema yang dipakai klinik hari ini semuanya jadi baris di sini:
 *   1. fee per pasien             -> per_patient,     amount 5000
 *   2. bonus pasien baru          -> per_new_patient, amount 5000
 *   3. target penjualan 5%        -> revenue_percent, percent 5,  min 0
 *   4. omzet 10 juta ke atas 6%   -> revenue_percent, percent 6,  min 10.000.000
 *
 * Baris 3 dan 4 adalah aturan sejenis dengan ambang berbeda; yang dipakai
 * adalah ambang tertinggi yang terlampaui, sehingga menambah tingkat baru
 * cukup menambah baris — tanpa menyentuh kode sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            // Dipakai aturan nominal tetap (per pasien / per pasien baru).
            $table->decimal('amount', 12, 2)->default(0);
            // Dipakai aturan persentase omzet.
            $table->decimal('percent', 5, 2)->default(0);
            // Ambang omzet minimum agar aturan ini berlaku.
            $table->decimal('min_revenue', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
