<?php

namespace App\Actions\Dashboard;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Angka ringkas untuk kepala dasbor. Semuanya dihitung ulang setiap kali
 * dasbor dibuka — bukan disimpan — supaya tidak pernah basi.
 *
 * Konvensi omzet mengikuti ReportService: hanya transaksi lunas dan belum
 * dibatalkan yang dihitung (FR-059, FR-070).
 */
class GetDashboardKpisAction
{
    /**
     * @return array{kpis: array<string, mixed>, booking_status_counts: array<int, array<string, mixed>>}
     */
    public function handle(int $tenantId): array
    {
        $today = Carbon::today();

        return [
            'kpis' => [
                'revenue_today' => $this->revenueToday($tenantId, $today),
                'bookings_today' => $this->bookingsToday($tenantId, $today),
                'new_patients_7d' => $this->newPatients($tenantId, $today),
                'outstanding_amount' => $this->outstanding($tenantId),
                'low_stock_count' => $this->lowStock($tenantId),
            ],
            'booking_status_counts' => $this->bookingStatusCounts($tenantId),
        ];
    }

    private function revenueToday(int $tenantId, Carbon $today): string
    {
        $total = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereNull('cancelled_at')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->sum('subtotal');

        return sprintf('%.2f', (float) $total);
    }

    private function bookingsToday(int $tenantId, Carbon $today): int
    {
        return DB::table('bookings')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->whereBetween('start_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->count();
    }

    private function newPatients(int $tenantId, Carbon $today): int
    {
        return DB::table('patients')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $today->copy()->subDays(6)->startOfDay())
            ->count();
    }

    /**
     * Sisa tagihan yang belum tertutup. Kelebihan bayar satu transaksi tidak
     * boleh menutupi kekurangan transaksi lain, jadi dijumlahkan per baris.
     */
    private function outstanding(int $tenantId): string
    {
        $rows = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->whereNull('cancelled_at')
            ->whereNull('deleted_at')
            ->where('payment_status', '!=', PaymentStatus::Paid->value)
            ->get(['subtotal', 'paid_amount']);

        $total = $rows->sum(
            fn ($row) => max(0, (float) $row->subtotal - (float) $row->paid_amount),
        );

        return sprintf('%.2f', $total);
    }

    private function lowStock(int $tenantId): int
    {
        return DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereColumn('stock_balance', '<=', 'min_threshold')
            ->count();
    }

    /**
     * Semua status selalu ikut terkirim, termasuk yang nol — donut yang
     * kategorinya hilang-timbul lebih sulit dibaca daripada yang tetap.
     *
     * @return array<int, array<string, mixed>>
     */
    private function bookingStatusCounts(int $tenantId): array
    {
        $counts = DB::table('bookings')
            ->where('tenant_id', $tenantId)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status');

        return array_map(
            fn (BookingStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ],
            BookingStatus::cases(),
        );
    }
}
