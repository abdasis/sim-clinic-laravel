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
