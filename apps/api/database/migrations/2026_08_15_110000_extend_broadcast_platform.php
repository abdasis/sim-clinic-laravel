<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perluasan platform WhatsApp:
 * - broadcasts memegang lifecycle (siap → mengirim → jeda/batal/selesai) dan
 *   jenisnya: promosi tunduk pada izin pasien, pengingat operasional tidak.
 * - message_templates: templat pesan yang bisa dipakai ulang.
 * - reminder_rules: master "treatment X diingatkan setelah N hari".
 * - broadcast_recipients mencatat percobaan kirim dan aturan pengingat
 *   asalnya, supaya pengingat yang sama tidak terkirim dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->string('kind')->default('promo')->after('title');
            $table->string('status')->default('ready')->after('kind');
        });

        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->foreignId('reminder_rule_id')->nullable()->after('patient_id');

            $table->index(['patient_id', 'reminder_rule_id']);
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->unsignedInteger('days_after');
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Satu layanan satu jadwal pengingat; dua jadwal untuk layanan
            // yang sama pasti menghasilkan pesan ganda.
            $table->unique('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_rules');
        Schema::dropIfExists('message_templates');

        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->dropIndex(['patient_id', 'reminder_rule_id']);
            $table->dropColumn(['attempts', 'reminder_rule_id']);
        });

        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn(['kind', 'status']);
        });
    }
};
