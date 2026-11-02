<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konteks balasan (pesan yang dikutip) dan reaksi emoji pada sebuah pesan.
 *
 * `reply_to_message_id` menunjuk pesan lain di utas yang sama; pesan induk
 * yang dihapus tidak boleh ikut menghapus balasannya, jadi relasinya
 * nullOnDelete. Reaksi disimpan pada pesan yang diberi reaksi — kosong
 * berarti reaksinya sudah dicabut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reply_to_message_id')
                ->nullable()
                ->after('body')
                ->constrained('messages')
                ->nullOnDelete();
            $table->string('reaction', 16)->nullable()->after('reply_to_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to_message_id']);
            $table->dropColumn(['reply_to_message_id', 'reaction']);
        });
    }
};
