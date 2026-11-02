<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pesan di dalam satu percakapan, masuk maupun keluar.
 *
 * `provider_message_id` unik per percakapan: gateway mengirim ulang webhook
 * yang tidak dibalas 200, dan tanpa kunci ini satu pesan pasien tercatat
 * berkali-kali sambil menaikkan penghitung belum dibaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->string('direction', 8);
            $table->text('body');
            $table->string('provider_message_id', 128)->nullable();
            $table->string('status', 20)->default('sent');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['conversation_id', 'sent_at']);
            $table->unique(['conversation_id', 'provider_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
