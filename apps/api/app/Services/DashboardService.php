<?php

namespace App\Services;

use App\Actions\Dashboard\GetDashboardKpisAction;
use App\Actions\Dashboard\GetRevenueAnalyticsAction;
use App\Actions\Dashboard\GetTodayScheduleAction;
use Illuminate\Support\Carbon;

/**
 * Perakit dasbor klinik: satu panggilan mengembalikan seluruh isi halaman.
 * Dasbor menampilkan lima blok sekaligus, jadi memecahnya jadi lima request
 * hanya membuat halaman berkedip saat dimuat.
 */
class DashboardService
{
    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function summary(int $days): array
    {
        $tenantId = app('tenant')->id;

        $to = Carbon::today();
        $from = $to->copy()->subDays($days - 1);

        $kpis = app(GetDashboardKpisAction::class)->handle($tenantId);
        $revenue = app(GetRevenueAnalyticsAction::class)->handle($tenantId, $from, $to);

        return [
            'data' => [
                ...$kpis,
                ...$revenue,
                'schedule_today' => app(GetTodayScheduleAction::class)->handle(),
            ],
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
            ],
        ];
    }
}
