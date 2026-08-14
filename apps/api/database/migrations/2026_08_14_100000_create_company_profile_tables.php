<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konten company profile publik per tenant (spec 010).
 *
 * Field narasi disimpan sebagai peta locale (`{"id": …, "en": …}`) supaya satu
 * baris melayani dua bahasa tanpa tabel terjemahan terpisah. Kolomnya `json`,
 * bukan `jsonb`, agar migration ini tetap jalan di SQLite saat test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profile_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->boolean('is_published')->default(false);
            $table->json('brand_name')->nullable();
            $table->json('tagline')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->json('address')->nullable();
            $table->string('map_embed_url', 1000)->nullable();
            $table->json('social_links')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->boolean('chat_widget_enabled')->default(false);
            $table->string('chat_widget_number')->nullable();
            $table->timestamps();

            // Satu tenant satu setelan; CMS memperlakukannya sebagai singleton.
            $table->unique('tenant_id');
        });

        Schema::create('company_navigation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('position')->default('header');
            $table->json('label');
            $table->string('link_type')->default('anchor_section');
            $table->string('link_value', 1000);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'position', 'is_active', 'sort_order'], 'company_nav_tenant_position_idx');
        });

        Schema::create('company_profile_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->json('title')->nullable();
            $table->json('subtitle')->nullable();
            $table->string('image_path');
            $table->json('cta_label')->nullable();
            $table->string('cta_type')->nullable();
            $table->string('cta_value', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order'], 'company_slides_tenant_active_idx');
        });

        Schema::create('company_value_props', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('icon')->nullable();
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
            // Etalase boleh menampilkan tindakan yang belum jadi master layanan.
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('slug');
            $table->json('name');
            $table->json('excerpt')->nullable();
            $table->json('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('badge')->nullable();
            $table->string('price_label')->nullable();
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
            $table->string('cta_type')->nullable();
            $table->string('cta_value', 1000)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order'], 'company_promos_tenant_active_idx');
        });

        Schema::create('company_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('logo_path');
            $table->string('url', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order'], 'company_brands_tenant_active_idx');
        });

        Schema::create('company_testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('author_name');
            $table->json('author_role')->nullable();
            $table->json('quote');
            $table->string('avatar_path')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order'], 'company_testimonials_tenant_active_idx');
        });

        Schema::create('company_content_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('section_key');
            $table->string('layout')->default('banner');
            $table->json('title')->nullable();
            $table->json('body')->nullable();
            $table->string('image_path')->nullable();
            $table->json('cta_label')->nullable();
            $table->string('cta_type')->nullable();
            $table->string('cta_value', 1000)->nullable();
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
