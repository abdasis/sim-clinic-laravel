<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\StatsModule;
use App\Enums\StockMovementType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * StatsService — ringkasan angka dan tren harian untuk kepala halaman index.
 *
 * Mengikuti pola ReportService: agregasi baca-saja lintas baris lewat query
 * builder, di-scope ke tenant aktif (app('tenant')->id). Tidak ada tulis, jadi
 * tidak lewat Action dan tidak menghasilkan audit log.
 *
 * Bentuk balikan tiap modul seragam supaya satu komponen frontend cukup:
 * { kpis: [{key,label,value,unit}], trend: [{date,value}], trend_metric }.
 */
class StatsService
{
    /** Balikan kosong lebih membingungkan daripada deret nol yang rapi. */
    private const UNIT_COUNT = 'count';

    private const UNIT_CURRENCY = 'currency';

    private const UNIT_QTY = 'qty';

    /** Tiga bulan cukup untuk melihat arah pertumbuhan tenant tanpa berdesakan. */
    private const CENTRAL_WEEKS = 12;

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function forModule(StatsModule $module, int $days): array
    {
        $tenantId = app('tenant')->id;
        $to = Carbon::today();
        $from = $to->copy()->subDays($days - 1);

        try {
            $data = match ($module) {
                StatsModule::Patients => $this->patients($tenantId, $from, $to),
                StatsModule::Services => $this->services($tenantId, $from, $to),
                StatsModule::Products => $this->products($tenantId, $from, $to),
                StatsModule::Inventory => $this->inventory($tenantId, $from, $to),
                StatsModule::MedicalRecords => $this->medicalRecords($tenantId, $from, $to),
                StatsModule::Transactions => $this->transactions($tenantId, $from, $to),
                StatsModule::Bookings => $this->bookings($tenantId, $from, $to),
                StatsModule::Staff => $this->staff($tenantId, $from, $to),
                StatsModule::ActivityLogs => $this->activityLogs($tenantId, $from, $to),
            };
        } catch (Throwable $e) {
            Log::error('stats aggregation', [
                'module' => $module->value,
                'tenant_id' => $tenantId,
                'exception' => $e,
            ]);

            throw $e;
        }

        return [
            'data' => $data,
            'meta' => [
                'module' => $module->value,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
            ],
        ];
    }

    // =====================================================================
    // Modul
    // =====================================================================

    /** @return array<string, mixed> */
    private function patients(int $tenantId, Carbon $from, Carbon $to): array
    {
        $base = fn () => DB::table('patients')->where('tenant_id', $tenantId)->whereNull('deleted_at');

        return $this->payload(
            kpis: [
                $this->kpi('total', 'patients.total', $base()->count()),
                $this->kpi('new_7d', 'patients.new_7d', $base()->where('created_at', '>=', $to->copy()->subDays(6)->startOfDay())->count()),
                $this->kpi('new_30d', 'patients.new_30d', $base()->where('created_at', '>=', $to->copy()->subDays(29)->startOfDay())->count()),
            ],
            trendMetric: 'patients_new',
            trend: $this->dailyTrend($base(), 'created_at', 'COUNT(*)', $from, $to),
        );
    }

    /** @return array<string, mixed> */
    private function services(int $tenantId, Carbon $from, Carbon $to): array
    {
        $base = fn () => DB::table('services')->where('tenant_id', $tenantId);
        $avgPrice = (float) $base()->where('status', 'active')->avg('price');

        // Tren layanan diukur dari yang benar-benar laku, bukan dari jumlah
        // katalognya — katalog nyaris tidak berubah harian.
        $sold = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.tenant_id', $tenantId)
            ->where('transactions.payment_status', PaymentStatus::Paid->value)
            ->whereNull('transactions.cancelled_at')
            ->whereNull('transactions.deleted_at')
            ->whereNotNull('transaction_items.service_id');

        return $this->payload(
            kpis: [
                $this->kpi('active', 'services.active', $base()->where('status', 'active')->count()),
                $this->kpi('archived', 'services.archived', $base()->where('status', 'archived')->count()),
                $this->kpi('avg_price', 'services.avg_price', round($avgPrice, 2), self::UNIT_CURRENCY),
            ],
            trendMetric: 'service_sales',
            trend: $this->dailyTrend($sold, 'transactions.created_at', 'COALESCE(SUM(transaction_items.qty), 0)', $from, $to),
        );
    }

    /** @return array<string, mixed> */
    private function products(int $tenantId, Carbon $from, Carbon $to): array
    {
        $base = fn () => DB::table('products')->where('tenant_id', $tenantId);
        $movements = DB::table('stock_movements')->where('tenant_id', $tenantId);

        return $this->payload(
            kpis: [
                $this->kpi('total', 'products.total', $base()->count()),
                $this->kpi('low_stock', 'products.low_stock', $base()->where('status', 'active')->whereColumn('stock_balance', '<=', 'min_threshold')->count()),
                $this->kpi('archived', 'products.archived', $base()->where('status', 'archived')->count()),
            ],
            trendMetric: 'product_movements',
            trend: $this->dailyTrend($movements, 'created_at', 'COUNT(*)', $from, $to),
        );
    }

    /** @return array<string, mixed> */
    private function inventory(int $tenantId, Carbon $from, Carbon $to): array
    {
        $window = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $base = fn () => DB::table('stock_movements')->where('tenant_id', $tenantId)->whereBetween('created_at', $window);
        $outTypes = [StockMovementType::OutManual->value, StockMovementType::SoldPos->value];

        return $this->payload(
            kpis: [
                $this->kpi('movements', 'inventory.movements', $base()->count()),
                $this->kpi('total_in', 'inventory.total_in', (int) $base()->whereIn('type', [StockMovementType::In->value, StockMovementType::Rollback->value])->sum('quantity'), self::UNIT_QTY),
                $this->kpi('total_out', 'inventory.total_out', (int) $base()->whereIn('type', $outTypes)->sum('quantity'), self::UNIT_QTY),
            ],
            trendMetric: 'inventory_net',
            // Masuk dihitung positif, keluar negatif, jadi garisnya membaca arah
            // stok — bukan sekadar seberapa ramai pergerakannya.
            trend: $this->dailyTrend(
                DB::table('stock_movements')->where('tenant_id', $tenantId),
                'created_at',
                "COALESCE(SUM(CASE WHEN type IN ('in', 'rollback') THEN quantity ELSE -quantity END), 0)",
                $from,
                $to,
            ),
        );
    }

    /** @return array<string, mixed> */
    private function medicalRecords(int $tenantId, Carbon $from, Carbon $to): array
    {
        $base = fn () => DB::table('medical_records')->where('tenant_id', $tenantId)->whereNull('deleted_at');

        return $this->payload(
            kpis: [
                $this->kpi('total', 'medical_records.total', $base()->count()),
                $this->kpi('this_week', 'medical_records.this_week', $base()->where('created_at', '>=', $to->copy()->startOfWeek())->count()),
                $this->kpi('authors', 'medical_records.authors', $base()->where('created_at', '>=', $from->copy()->startOfDay())->distinct()->count('author_id')),
            ],
            trendMetric: 'medical_records_new',
            trend: $this->dailyTrend($base(), 'created_at', 'COUNT(*)', $from, $to),
        );
    }

    /** @return array<string, mixed> */
    private function transactions(int $tenantId, Carbon $from, Carbon $to): array
    {
        $window = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $base = fn () => DB::table('transactions')->where('tenant_id', $tenantId)->whereNull('deleted_at');
        $paid = fn () => $base()->where('payment_status', PaymentStatus::Paid->value)->whereNull('cancelled_at');

        return $this->payload(
            kpis: [
                $this->kpi('revenue', 'transactions.revenue', round((float) $paid()->whereBetween('created_at', $window)->sum('subtotal'), 2), self::UNIT_CURRENCY),
                $this->kpi('outstanding', 'transactions.outstanding', $this->outstanding($tenantId), self::UNIT_CURRENCY),
                $this->kpi('paid_count', 'transactions.paid_count', $paid()->whereBetween('created_at', $window)->count()),
                $this->kpi('cancelled_count', 'transactions.cancelled_count', $base()->whereNotNull('cancelled_at')->whereBetween('created_at', $window)->count()),
            ],
            trendMetric: 'transactions_revenue',
            trend: $this->dailyTrend($paid(), 'created_at', 'COALESCE(SUM(subtotal), 0)', $from, $to, currency: true),
        );
    }

    /** @return array<string, mixed> */
    private function bookings(int $tenantId, Carbon $from, Carbon $to): array
    {
        $base = fn () => DB::table('bookings')->where('tenant_id', $tenantId);

        return $this->payload(
            kpis: [
                $this->kpi('today', 'bookings.today', $base()->where('status', '!=', BookingStatus::Cancelled->value)->whereBetween('start_at', [$to->copy()->startOfDay(), $to->copy()->endOfDay()])->count()),
                $this->kpi('this_week', 'bookings.this_week', $base()->where('status', '!=', BookingStatus::Cancelled->value)->whereBetween('start_at', [$to->copy()->startOfWeek(), $to->copy()->endOfWeek()])->count()),
                $this->kpi('pending', 'bookings.pending', $base()->where('status', BookingStatus::Pending->value)->count()),
                $this->kpi('done', 'bookings.done', $base()->where('status', BookingStatus::Done->value)->count()),
            ],
            trendMetric: 'bookings_new',
            trend: $this->dailyTrend($base(), 'created_at', 'COUNT(*)', $from, $to),
        );
    }

    /** @return array<string, mixed> */
    private function staff(int $tenantId, Carbon $from, Carbon $to): array
    {
        $base = fn () => DB::table('users')->where('tenant_id', $tenantId)->whereNull('deleted_at');

        return $this->payload(
            kpis: [
                $this->kpi('total', 'staff.total', $base()->count()),
                $this->kpi('active', 'staff.active', $base()->where('status', 'active')->count()),
                // Berapa dari empat peran klinik yang sudah ada orangnya —
                // pengingat halus kalau kasir atau dokter belum terisi.
                $this->kpi('roles_filled', 'staff.roles_filled', $base()->whereNotNull('clinic_role')->distinct()->count('clinic_role')),
            ],
            trendMetric: 'staff_new',
            trend: $this->dailyTrend($base(), 'created_at', 'COUNT(*)', $from, $to),
        );
    }

    /**
     * Log aktivitas: seberapa ramai kliniknya dan siapa yang menggerakkan.
     *
     * Tenant-nya ada di properties->tenant_id, bukan kolom — penyaringnya
     * memakai JSON where supaya tetap satu bentuk di SQLite dan PostgreSQL.
     *
     * @return array<string, mixed>
     */
    private function activityLogs(int $tenantId, Carbon $from, Carbon $to): array
    {
        $base = fn () => DB::table('audit_logs')->where('properties->tenant_id', $tenantId);
        $inRange = fn () => $base()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        return $this->payload(
            kpis: [
                $this->kpi('total', 'activity_logs.total', $inRange()->count()),
                $this->kpi('today', 'activity_logs.today', $base()->where('created_at', '>=', $to->copy()->startOfDay())->count()),
                $this->kpi('actors', 'activity_logs.actors', $inRange()->whereNotNull('causer_id')->distinct()->count('causer_id')),
                $this->kpi('modules', 'activity_logs.modules', $inRange()->whereNotNull('log_name')->distinct()->count('log_name')),
            ],
            trendMetric: 'activity_logs_daily',
            trend: $this->dailyTrend($base(), 'created_at', 'COUNT(*)', $from, $to),
        );
    }

    /**
     * Statistik platform (lintas tenant) untuk halaman central. Bukan
     * tenant-scoped, jadi sengaja terpisah dari forModule().
     *
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function central(int $weeks = self::CENTRAL_WEEKS): array
    {
        $to = Carbon::today()->endOfWeek();
        $from = $to->copy()->subWeeks($weeks - 1)->startOfWeek();

        try {
            $base = fn () => DB::table('tenants');

            $data = $this->payload(
                kpis: [
                    $this->kpi('total', 'central.total', $base()->count()),
                    $this->kpi('active', 'central.active', $base()->where('status', 'active')->count()),
                    $this->kpi('inactive', 'central.inactive', $base()->where('status', 'inactive')->count()),
                    $this->kpi('new_this_month', 'central.new_this_month', $base()->where('created_at', '>=', Carbon::today()->startOfMonth())->count()),
                ],
                trendMetric: 'tenants_new',
                trend: $this->weeklyTrend($base(), $from, $to),
            );
        } catch (Throwable $e) {
            Log::error('stats aggregation', ['module' => 'central', 'exception' => $e]);

            throw $e;
        }

        return [
            'data' => $data,
            'meta' => [
                'module' => 'central',
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'weeks' => $weeks,
            ],
        ];
    }

    // =====================================================================
    // Pembantu
    // =====================================================================

    /**
     * Tenant baru datang hitungan minggu, bukan harian — deret harian akan
     * hampir seluruhnya nol dan grafiknya jadi datar tak berarti.
     *
     * ponytail: raw query for daily group-by aggregation — pengelompokan
     * dilakukan di PHP karena fungsi minggu berbeda antar-driver (`strftime`
     * di SQLite, `date_trunc` di PostgreSQL).
     *
     * @param  Builder  $query
     * @return array<int, array<string, mixed>>
     */
    private function weeklyTrend($query, Carbon $from, Carbon $to): array
    {
        $created = $query
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->pluck('created_at')
            ->map(fn ($value) => Carbon::parse($value)->startOfWeek()->toDateString())
            ->countBy();

        $trend = [];

        for ($week = $from->copy(); $week->lte($to); $week->addWeek()) {
            $key = $week->copy()->startOfWeek()->toDateString();

            $trend[] = ['date' => $key, 'value' => (int) ($created[$key] ?? 0)];
        }

        return $trend;
    }

    /**
     * Sisa tagihan yang belum tertutup, sejalan dengan dasbor: kelebihan bayar
     * satu transaksi tidak menutupi kekurangan transaksi lain.
     */
    private function outstanding(int $tenantId): float
    {
        $rows = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->whereNull('cancelled_at')
            ->whereNull('deleted_at')
            ->where('payment_status', '!=', PaymentStatus::Paid->value)
            ->get(['subtotal', 'paid_amount']);

        return round($rows->sum(fn ($row) => max(0, (float) $row->subtotal - (float) $row->paid_amount)), 2);
    }

    /**
     * Satu titik per hari dalam rentang, tanggal tanpa data tetap dikirim
     * bernilai nol supaya garisnya kontinu dan jeda terbaca apa adanya.
     *
     * ponytail: raw query for daily group-by aggregation — `DATE()` dipakai
     * karena tersedia di SQLite maupun PostgreSQL. Pindah ke `date_trunc`
     * bila kelak butuh granularitas jam pada PostgreSQL saja.
     *
     * @param  Builder  $query  sudah ter-scope tenant
     * @return array<int, array<string, mixed>>
     */
    private function dailyTrend($query, string $column, string $aggregate, Carbon $from, Carbon $to, bool $currency = false): array
    {
        $rows = $query
            ->whereBetween($column, [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy(DB::raw("DATE({$column})"))
            ->selectRaw("DATE({$column}) as day, {$aggregate} as value")
            ->pluck('value', 'day');

        $trend = [];

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $key = $date->toDateString();
            $raw = $rows[$key] ?? 0;

            $trend[] = [
                'date' => $key,
                'value' => $currency ? round((float) $raw, 2) : (int) $raw,
            ];
        }

        return $trend;
    }

    /**
     * @param  array<int, array<string, mixed>>  $kpis
     * @param  array<int, array<string, mixed>>  $trend
     * @return array<string, mixed>
     */
    private function payload(array $kpis, string $trendMetric, array $trend): array
    {
        return [
            'kpis' => $kpis,
            'trend' => $trend,
            'trend_metric' => $trendMetric,
            'trend_label' => __("stats.trend.{$trendMetric}"),
        ];
    }

    /** @return array<string, mixed> */
    private function kpi(string $key, string $labelKey, float|int $value, string $unit = self::UNIT_COUNT): array
    {
        return [
            'key' => $key,
            'label' => __("stats.{$labelKey}"),
            'value' => $value,
            'unit' => $unit,
        ];
    }
}
