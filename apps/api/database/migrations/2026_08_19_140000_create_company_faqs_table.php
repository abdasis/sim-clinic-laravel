<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanya-jawab di halaman publik klinik.
 *
 * Pertanyaan yang sama ditanyakan berulang lewat WhatsApp — sakit tidak,
 * berapa lama, perlu janji dulu atau tidak. Menjawabnya di halaman berarti
 * calon pasien tidak perlu menunggu balasan untuk memutuskan datang.
 *
 * Boleh ditempelkan ke satu treatment lewat `company_treatment_id`, atau
 * dibiarkan kosong untuk pertanyaan umum yang tampil di beranda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // Dihapus bersama treatment-nya: jawaban tentang treatment yang
            // sudah tidak ditawarkan hanya menyesatkan.
            $table->foreignId('company_treatment_id')
                ->nullable()
                ->constrained('company_treatments')
                ->cascadeOnDelete();
            $table->json('question');
            $table->json('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order'], 'company_faqs_tenant_active_idx');
            $table->index(['tenant_id', 'company_treatment_id'], 'company_faqs_tenant_treatment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_faqs');
    }
};
