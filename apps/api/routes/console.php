<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pengingat perawatan ulang: disusun tiap pagi sebelum klinik ramai.
Schedule::command('clinic:send-reminders')->dailyAt('08:00');
