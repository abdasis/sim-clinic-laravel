<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konten company profile publik per tenant (spec 010, data-model.md).
 *
 * Field teks yang dibaca pengunjung disimpan sebagai peta locale
 * (`{"id": …, "en": …}`) supaya satu baris melayani dua bahasa tanpa tabel
 * terjemahan terpisah. Kolomnya `json`, bukan `jsonb`, agar migration ini
 * tetap jalan di SQLite saat test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profile_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('logo_path')->nullable();
            $table->json('site_name')->nullable();
            $table->string('copyright_text')->nullable();
            $table->json('chat_channels')->nullable();
            $table->json('social_links')->nullable();
            $table->json('marketplace_links')->nullable();
            $table->string('default_locale', 5)->default('id');
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            // Satu tenant satu setelan; CMS memperlakukannya sebagai singleton.
            $table->unique('tenant_id');
        });

        Schema::create('company_navigation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->json('label');
            $table->string('url')->nullable();
            $table->string('link_type');
            $table->string('position');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // Satu-dua menu tampil sebagai tombol, bukan tautan biasa.
            $table->boolean('is_cta')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'position', 'is_active', 'sort_order'], 'company_nav_tenant_position_idx');
        });

        Schema::create('company_profile_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->string('image_path')->nullable();
            $table->json('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('cta_type')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order'], 'company_slides_tenant_active_idx');
        });

        Schema::create('company_value_props', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('icon', 100)->nullable();
            $table->json('title');
            $table->json('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order'], 'company_value_props_tenant_active_idx');
        });

        Schema::create('company_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // Etalase boleh menampilkan tindakan yang belum jadi master layanan,
            // dan menghapus master tidak boleh menjatuhkan halaman publik.
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('slug');
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('badge')->nullable();
            $table->json('category_tags')->nullable();
            $table->string('detail_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active', 'sort_order'], 'company_treatments_tenant_active_idx');
        });

        Schema::create('company_promos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('image_path')->nullable();
            $table->json('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('cta_type')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order'], 'company_promos_tenant_active_idx');
        });

        Schema::create('company_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('external_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order'], 'company_brands_tenant_active_idx');
        });

        Schema::create('company_testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->json('quote');
            $table->string('author_name');
            $table->unsignedSmallInteger('since_year')->nullable();
            $table->string('avatar_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order'], 'company_testimonials_tenant_active_idx');
        });

        Schema::create('company_content_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('section_key', 100);
            $table->json('title')->nullable();
            $table->json('body')->nullable();
            $table->string('image_path')->nullable();
            $table->json('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('cta_type')->nullable();
            $table->string('layout_type')->default('split');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Satu slot satu isi; frontend merender per section_key.
            $table->unique(['tenant_id', 'section_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_content_sections');
        Schema::dropIfExists('company_testimonials');
        Schema::dropIfExists('company_brands');
        Schema::dropIfExists('company_promos');
        Schema::dropIfExists('company_treatments');
        Schema::dropIfExists('company_value_props');
        Schema::dropIfExists('company_profile_slides');
        Schema::dropIfExists('company_navigation_items');
        Schema::dropIfExists('company_profile_settings');
    }
};
