<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris pengingat per booking.
 *
 * Baris inilah yang menentukan apakah pengingat jadi dikirim, bukan job yang
 * sudah terlanjur antre: job tertunda tidak bisa ditarik kembali dari antrian.
 * Saat jadwalnya diubah atau bookingnya dibatalkan, barisnya yang diperbarui,
 * dan job lama yang kemudian bangun akan mendapati dirinya sudah tidak
 * berlaku lalu berhenti sendiri.
 *
 * Unique pada booking_id: satu jadwal tidak boleh melahirkan dua pengingat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->timestamp('remind_at');
            // Disalin saat dijadwalkan, bukan dibaca ulang saat kirim: setelan
            // yang diubah admin besok tidak boleh menggeser pengingat yang
            // sudah dijanjikan hari ini.
            $table->unsignedInteger('reminder_offset_minutes');
            $table->enum('status', ['pending', 'sent', 'failed', 'cancelled'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reminders');
    }
};
