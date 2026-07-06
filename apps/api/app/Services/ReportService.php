<?php

namespace App\Services;

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
}
