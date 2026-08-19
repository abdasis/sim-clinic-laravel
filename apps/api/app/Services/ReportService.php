<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Transaction;
use App\Support\CommissionCalculator;
use App\Support\TenantCache;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * ReportService — agregasi laporan omzet, treatment, dan produk (US8, FR-070..074).
 *
 * Semua query di-scope ke tenant aktif (app('tenant')->id) dan rentang tanggal lokal
 * tenant [from 00:00:00 .. to 23:59:59]. Hanya transaksi lunas & belum dibatalkan
 * (payment_status='paid' AND cancelled_at IS NULL) yang dihitung (FR-059, FR-073).
 */
class ReportService
{
    private function tenantId(): int
    {
        return app('tenant')->id;
    }

    /**
     * Bungkus satu laporan dengan cache berumur pendek.
     *
     * Umurnya sengaja hanya semenit, bukan sehari untuk periode yang sudah
     * lewat. Alasannya ada di POS: tanggal terbit transaksi boleh dimundurkan
     * untuk mencatat penjualan yang terlewat, jadi laporan bulan lalu masih
     * bisa berubah hari ini. Menyimpannya lama berarti ada kemungkinan
     * akuntan membaca angka yang sudah tidak benar tanpa tahu.
     *
     * Yang diredam cukup: membuka laporan, mengganti tab, lalu kembali —
     * pola yang menghasilkan tiga kali perhitungan penuh dalam sepuluh detik.
     *
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private function cached(string $name, string $from, string $to, Closure $build): array
    {
        return TenantCache::remember(
            'report:'.$name.':'.$from.':'.$to,
            TenantCache::TTL_LIVE,
            $build,
        );
    }

    public function revenue(string $from, string $to): array
    {
        return $this->cached('revenue', $from, $to, fn () => $this->buildRevenue($from, $to));
    }

    private function buildRevenue(string $from, string $to): array
    {
        $tenantId = $this->tenantId();
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $row = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.tenant_id', $tenantId)
            ->where('transactions.payment_status', 'paid')
            ->whereNull('transactions.cancelled_at')
            ->whereBetween('transactions.created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(transaction_items.subtotal), 0) as total_revenue')
            ->selectRaw('COUNT(DISTINCT transactions.id) as paid_transactions_count')
            ->first();

        return [
            'total_revenue' => sprintf('%.2f', (float) ($row->total_revenue ?? 0)),
            'paid_transactions_count' => (int) ($row->paid_transactions_count ?? 0),
            'from' => $from,
            'to' => $to,
        ];
    }

    public function servicesReport(string $from, string $to): array
    {
        return $this->cached('servicesReport', $from, $to, fn () => $this->buildServicesReport($from, $to));
    }

    private function buildServicesReport(string $from, string $to): array
    {
        $tenantId = $this->tenantId();
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $rows = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.tenant_id', $tenantId)
            ->where('transactions.payment_status', 'paid')
            ->whereNull('transactions.cancelled_at')
            ->whereBetween('transactions.created_at', [$start, $end])
            ->whereNotNull('transaction_items.service_id')
            ->groupBy('transaction_items.service_id', 'transaction_items.name')
            ->selectRaw('transaction_items.service_id as service_id')
            ->selectRaw('transaction_items.name as service_name')
            ->selectRaw('SUM(transaction_items.qty) as qty_sold')
            ->selectRaw('SUM(transaction_items.subtotal) as revenue')
            ->get();

        return $rows->map(fn ($r) => [
            'service_id' => (int) $r->service_id,
            'service_name' => $r->service_name,
            'qty_sold' => (int) $r->qty_sold,
            'revenue' => sprintf('%.2f', (float) $r->revenue),
        ])->all();
    }

    public function productsReport(string $from, string $to): array
    {
        return $this->cached('productsReport', $from, $to, fn () => $this->buildProductsReport($from, $to));
    }

    private function buildProductsReport(string $from, string $to): array
    {
        $tenantId = $this->tenantId();
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $rows = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.tenant_id', $tenantId)
            ->where('transactions.payment_status', 'paid')
            ->whereNull('transactions.cancelled_at')
            ->whereBetween('transactions.created_at', [$start, $end])
            ->whereNotNull('transaction_items.product_id')
            ->groupBy('transaction_items.product_id', 'transaction_items.name')
            ->selectRaw('transaction_items.product_id as product_id')
            ->selectRaw('transaction_items.name as product_name')
            ->selectRaw('SUM(transaction_items.qty) as qty_sold')
            ->selectRaw('SUM(transaction_items.subtotal) as revenue')
            ->get();

        return $rows->map(fn ($r) => [
            'product_id' => (int) $r->product_id,
            'product_name' => $r->product_name,
            'qty_sold' => (int) $r->qty_sold,
            'revenue' => sprintf('%.2f', (float) $r->revenue),
        ])->all();
    }

    /**
     * Laporan bulanan lengkap, mengikuti susunan laporan spreadsheet klinik:
     * baris per transaksi, rincian dana per metode bayar, rincian pengeluaran,
     * fee terapis, lalu keuntungan bersih.
     *
     * Keuntungan bersih = pendapatan - pengeluaran tercatat. Fee terapis
     * ditampilkan sebagai pratinjau dan TIDAK ikut dikurangkan otomatis —
     * begitu dibukukan, ia sudah masuk sebagai pengeluaran, dan mengurangkan
     * dua kali berarti melaporkan laba lebih kecil dari kenyataan.
     */
    public function monthly(string $from, string $to): array
    {
        return $this->cached('monthly', $from, $to, fn () => $this->buildMonthly($from, $to));
    }

    private function buildMonthly(string $from, string $to): array
    {
        $tenantId = $this->tenantId();
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $commission = (new CommissionCalculator($from, $to))->run();
        $feePerTransaction = $commission['by_transaction'];

        $transactions = Transaction::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereNull('cancelled_at')
            ->whereBetween('issued_at', [$start, $end])
            ->with(['items', 'patient', 'performers', 'payments'])
            ->orderBy('issued_at')
            ->get();

        $rows = $transactions->map(function (Transaction $transaction) use ($feePerTransaction) {
            $services = $transaction->items->whereNotNull('service_id');
            $products = $transaction->items->whereNotNull('product_id');
            $total = (float) $transaction->subtotal;
            // Fee baris ini sudah dihitung per nota oleh CommissionCalculator;
            // laporan klinik menampilkannya bersebelahan dengan totalnya.
            $fee = (float) ($feePerTransaction[$transaction->id] ?? 0);

            return [
                'issued_at' => $transaction->issued_at?->toDateString(),
                // Bisa lebih dari satu pelaksana; digabung supaya baris
                // laporannya tetap satu per transaksi.
                'therapist_name' => $transaction->performers->pluck('name')->implode(', ') ?: null,
                'patient_name' => $transaction->patient?->name,
                'invoice_number' => $transaction->invoice_number,
                // Klinik menulis cara bayarnya di sebelah nama pasien; di sini
                // dipisah jadi kolom sendiri supaya bisa disaring dan dijumlah.
                'payment_label' => $transaction->payments
                    ->map(fn (Payment $payment) => $payment->method->label())
                    ->unique()
                    ->implode(', ') ?: null,
                'services' => $services->pluck('name')->implode(', '),
                'products' => $products->pluck('name')->implode(', '),
                'treatment_amount' => (float) $services->sum('subtotal'),
                'product_amount' => (float) $products->sum('subtotal'),
                'total' => $total,
                'fee_amount' => $fee,
                'net_amount' => $total - $fee,
            ];
        })->values()->all();

        // Rincian dana per metode: dari pembayaran nyata, bukan asumsi.
        $paymentRows = DB::table('payments')
            ->join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->where('transactions.tenant_id', $tenantId)
            ->whereNull('transactions.cancelled_at')
            ->whereBetween('transactions.issued_at', [$start, $end])
            ->groupBy('payments.method')
            ->selectRaw('payments.method, SUM(payments.amount) as total')
            ->pluck('total', 'method');

        $payments = collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => [
            'method' => $method->value,
            'method_label' => $method->label(),
            'total' => (float) ($paymentRows[$method->value] ?? 0),
        ])->values()->all();

        // Kategori pengeluaran kini entitas, bukan kolom enum: pengelompokan
        // lewat pivot polimorfik. Pengeluaran tanpa kategori tetap muncul
        // sebagai satu baris — kalau disembunyikan, total per kategori tidak
        // akan pernah sama dengan total keseluruhan.
        $expenseRows = Expense::query()
            ->between($from, $to)
            ->leftJoin('categorizables', function ($join): void {
                $join->on('categorizables.categorizable_id', '=', 'expenses.id')
                    ->where('categorizables.categorizable_type', '=', 'expense');
            })
            ->leftJoin('categories', 'categories.id', '=', 'categorizables.category_id')
            ->selectRaw('categories.id as category_id, categories.name as category_name, SUM(expenses.amount) as total')
            ->groupBy('categories.id', 'categories.name')
            ->get();

        $expenses = $expenseRows->map(fn ($row) => [
            'category' => $row->category_id !== null ? (string) $row->category_id : null,
            'category_label' => $row->category_name ?? __('category.none'),
            'total' => (float) $row->total,
        ])->sortByDesc('total')->values()->all();

        $revenueTotal = (float) collect($rows)->sum('total');
        $expenseTotal = (float) $expenseRows->sum('total');

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => [
                'treatment' => (float) collect($rows)->sum('treatment_amount'),
                'product' => (float) collect($rows)->sum('product_amount'),
                'revenue' => $revenueTotal,
                'fee' => (float) collect($rows)->sum('fee_amount'),
                'net' => (float) collect($rows)->sum('net_amount'),
            ],
            // Angka pembuka laporan: berapa kali datang, berapa orang, berapa
            // yang baru pertama kali. Selama ini hanya bisa dihitung manual
            // dengan menyisir barisnya satu per satu.
            'summary' => [
                'visits' => count($rows),
                'patients' => collect($rows)->pluck('patient_name')->filter()->unique()->count(),
                'new_patients' => (int) collect($commission['rows'])->sum('new_patients'),
                'average_ticket' => count($rows) > 0 ? $revenueTotal / count($rows) : 0.0,
            ],
            'payments' => $payments,
            'expenses' => [
                'by_category' => $expenses,
                'total' => $expenseTotal,
            ],
            'commission' => [
                'rows' => $commission['rows'],
                'total' => $commission['total'],
            ],
            'net_profit' => $revenueTotal - $expenseTotal,
        ];
    }
}
