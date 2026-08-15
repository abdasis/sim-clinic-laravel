<?php

namespace App\Actions\Dashboard;

use App\Enums\PaymentStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tren omzet harian dan treatment terlaris. Keduanya agregasi lintas baris,
 * jadi memakai query builder langsung — bukan Eloquent per baris.
 */
class GetRevenueAnalyticsAction
{
    private const TOP_SERVICE_LIMIT = 5;

    /**
     * @return array{revenue_trend: array<int, array<string, mixed>>, top_services: array<int, array<string, mixed>>}
     */
    public function handle(int $tenantId, Carbon $from, Carbon $to): array
    {
        return [
            'revenue_trend' => $this->revenueTrend($tenantId, $from, $to),
            'top_services' => $this->topServices($tenantId, $from, $to),
        ];
    }

    /**
     * Hari tanpa transaksi tetap dikirim bernilai nol; kalau dilewati,
     * grafiknya akan memampatkan jeda dan tren terbaca lebih ramai.
     *
     * @return array<int, array<string, mixed>>
     */
    private function revenueTrend(int $tenantId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereNull('cancelled_at')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as day, SUM(subtotal) as revenue')
            ->pluck('revenue', 'day');

        $trend = [];

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $key = $date->toDateString();

            $trend[] = [
                'date' => $key,
                'revenue' => sprintf('%.2f', (float) ($rows[$key] ?? 0)),
            ];
        }

        return $trend;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topServices(int $tenantId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.tenant_id', $tenantId)
            ->where('transactions.payment_status', PaymentStatus::Paid->value)
            ->whereNull('transactions.cancelled_at')
            ->whereNull('transactions.deleted_at')
            ->whereBetween('transactions.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereNotNull('transaction_items.service_id')
            // Nama diambil dari snapshot item, jadi treatment yang sudah
            // diarsipkan tetap muncul dengan nama saat dijual.
            ->groupBy('transaction_items.name')
            ->selectRaw('transaction_items.name as service_name')
            ->selectRaw('SUM(transaction_items.qty) as qty')
            ->selectRaw('SUM(transaction_items.subtotal) as revenue')
            ->orderByDesc('revenue')
            ->limit(self::TOP_SERVICE_LIMIT)
            ->get();

        return $rows->map(fn ($row) => [
            'service_name' => $row->service_name,
            'qty' => (int) $row->qty,
            'revenue' => sprintf('%.2f', (float) $row->revenue),
        ])->all();
    }
}
