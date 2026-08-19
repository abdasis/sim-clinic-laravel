<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pengingat perawatan ulang: disusun tiap pagi sebelum klinik ramai.
Schedule::command('clinic:send-reminders')->dailyAt('08:00');

// Follow-up otomatis tingkat klinik. Diberi jarak setengah jam dari pengingat
// per-layanan supaya kiriman keduanya tidak menumpuk di menit yang sama.
Schedule::command('clinic:send-auto-reminders')->dailyAt('08:30');

// Percakapan chatbot yang menggantung ditutup tiap lima menit. Ambangnya
// milik masing-masing klinik; yang dijadwalkan di sini hanya pemeriksaannya,
// dan lima menit adalah resolusi terkasar yang masih terasa responsif untuk
// ambang sependek sepuluh menit.
Schedule::command('clinic:close-idle-chats')->everyFiveMinutes()->withoutOverlapping();

// Bersihkan arsip lama lebih dulu supaya ruang sudah lega saat dump baru
// ditulis — bukan sebaliknya, yang bisa gagal justru karena disk penuh.
Schedule::command('backup:clean')->dailyAt('01:00');

// Backup penuh (database + file aplikasi) dua kali sehari. Yang tengah hari
// memperpendek jarak kehilangan data bila sesuatu terjadi sore hari.
Schedule::command('backup:run')->dailyAt('01:30');
Schedule::command('backup:run')->dailyAt('13:30');

// Arsip yang menua atau menggemuk tanpa disadari sama saja dengan tidak
// punya backup, jadi kesehatannya diperiksa terjadwal dan dikabarkan.
Schedule::command('backup:monitor')->dailyAt('10:00');

// Cadangan basis data dini hari, saat klinik tutup dan kueri paling sepi.
// Prune menyusul lima belas menit kemudian supaya arsip baru sudah selesai
// ditulis sebelum yang lama dihitung umurnya.
Schedule::command('clinic:backup')->dailyAt('02:00')->description('Cadangan database harian');
Schedule::command('clinic:backup --prune=30')->dailyAt('02:15');
