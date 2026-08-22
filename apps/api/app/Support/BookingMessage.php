<?php

namespace App\Support;

use App\Models\Booking;

/**
 * Perakit teks pesan booking untuk pasien.
 *
 * Pengingat dan konfirmasi memakai daftar placeholder yang sama persis, dan
 * template racikan klinik dipakai bergantian di antara keduanya. Kalau
 * daftarnya ditulis dua kali, satu template yang menyebut :staf bisa terkirim
 * utuh lewat satu jalur dan mentah lewat jalur lain.
 *
 * Jam selesai sengaja tidak ada di daftar: yang ditunggu pasien adalah jam
 * kedatangannya, dan durasi treatment urusan sistem klinik.
 */
class BookingMessage
{
    public static function render(string $template, Booking $booking, string $clinicName): string
    {
        return strtr($template, [
            ':pasien' => $booking->patient?->name ?? 'Bapak/Ibu',
            ':klinik' => $clinicName,
            ':layanan' => $booking->service?->name ?? 'perawatan',
            ':staf' => $booking->assignee?->name ?? 'tim kami',
            ':tanggal' => $booking->start_at?->translatedFormat('l, d F Y') ?? '-',
            ':jam' => $booking->start_at?->format('H:i') ?? '-',
        ]);
    }
}
