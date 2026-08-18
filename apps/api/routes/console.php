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
