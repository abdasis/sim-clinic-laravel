<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\Transaction;
use App\Support\CommissionCalculator;
use Carbon\Carbon;
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

    public function revenue(string $from, string $to): array
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
        $tenantId = $this->tenantId();
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $transactions = Transaction::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereNull('cancelled_at')
            ->whereBetween('issued_at', [$start, $end])
            ->with(['items', 'patient', 'performers'])
            ->orderBy('issued_at')
            ->get();

        $rows = $transactions->map(function (Transaction $transaction) {
            $services = $transaction->items->whereNotNull('service_id');
            $products = $transaction->items->whereNotNull('product_id');

            return [
                'issued_at' => $transaction->issued_at?->toDateString(),
                // Bisa lebih dari satu pelaksana; digabung supaya baris
                // laporannya tetap satu per transaksi.
                'therapist_name' => $transaction->performers->pluck('name')->implode(', ') ?: null,
                'patient_name' => $transaction->patient?->name,
                'invoice_number' => $transaction->invoice_number,
                'services' => $services->pluck('name')->implode(', '),
                'products' => $products->pluck('name')->implode(', '),
                'treatment_amount' => (float) $services->sum('subtotal'),
                'product_amount' => (float) $products->sum('subtotal'),
                'total' => (float) $transaction->subtotal,
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

        $expenseRows = Expense::query()
            ->between($from, $to)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();

        $expenses = $expenseRows->map(fn ($row) => [
            'category' => $row->category?->value,
            'category_label' => $row->category?->label(),
            'total' => (float) $row->total,
        ])->sortByDesc('total')->values()->all();

        $commission = (new CommissionCalculator($from, $to))->run();

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
