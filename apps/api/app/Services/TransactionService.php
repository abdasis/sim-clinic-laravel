<?php

namespace App\Services;

use App\Actions\LogAuditAction;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Support\PromoPricing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi satu pintu pembuatan transaksi POS (US5):
 * snapshot harga/nama (FR-056), validasi & mutasi stok (FR-052, FR-053),
 * penerbitan invoice — semua dalam satu DB transaction.
 */
class TransactionService
{
    public function create(array $data): Transaction
    {
        $transaction = DB::transaction(function () use ($data): Transaction {
            $lines = $this->buildLines($data['items'] ?? []);
            $subtotal = array_sum(array_column($lines, 'subtotal'));

            $transaction = Transaction::create([
                'patient_id' => $data['patient_id'],
                'booking_id' => $data['booking_id'] ?? null,
                'cashier_id' => Auth::id(),
                // Nomor diambil di dalam transaction agar barisnya terkunci.
                'invoice_number' => Transaction::generateInvoiceNumber(),
                'subtotal' => $subtotal,
                'paid_amount' => 0,
                'payment_status' => PaymentStatus::Unpaid,
                'issued_at' => now(),
            ]);

            // Pelaksana kunjungan: dasar fee per pasien, bisa lebih dari satu.
            $transaction->syncPerformers($data['performer_ids'] ?? []);

            foreach ($lines as $line) {
                $transaction->items()->create([
                    'product_id' => $line['product_id'],
                    'service_id' => $line['service_id'],
                    // Penawar baris ini; dasar perhitungan target penjualan.
                    'offered_by' => $line['offered_by'],
                    'name' => $line['name'],
                    'unit_price' => $line['unit_price'],
                    'qty' => $line['qty'],
                    'subtotal' => $line['subtotal'],
                ]);

                if ($line['product'] !== null) {
                    app(StockService::class)->adjust(
                        $line['product'],
                        StockMovementType::SoldPos,
                        $line['qty'],
                        null,
                        $transaction,
                    );
                }
            }

            return $transaction->load('items', 'patient', 'performers');
        });

        app(LogAuditAction::class)->handle(
            'pos.transaction.created',
            $transaction,
            Auth::user(),
            ['attributes' => $transaction->getAttributes()],
            'Mencatat transaksi '.$transaction->invoice_number.'.',
        );

        return $transaction;
    }

    /**
     * Bangun baris item dari master (Service/Product) dengan snapshot nama+harga.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildLines(array $items): array
    {
        // Harga promo dihitung ulang di server, bukan diterima dari klien —
        // kasir tidak bisa menawar harga lewat payload.
        $pricing = new PromoPricing;
        $lines = [];

        foreach ($items as $item) {
            $qty = (int) $item['qty'];
            $line = isset($item['product_id']) && $item['product_id'] !== null
                ? $this->productLine((int) $item['product_id'], $qty, $pricing)
                : $this->serviceLine((int) $item['service_id'], $qty, $pricing);

            $line['offered_by'] = $item['offered_by'] ?? null;
            $lines[] = $line;
        }

        return $lines;
    }

    private function productLine(int $productId, int $qty, PromoPricing $pricing): array
    {
        $product = Product::findOrFail($productId);

        // Bahan pakai treatment habis di tangan terapis, bukan dibeli pasien.
        // Penjagaannya di sini, bukan sekadar di daftar katalog, supaya
        // permintaan yang menembak langsung pun tetap ditolak.
        if (! $product->type->isSellable()) {
            abort(422, __('pos.product_not_sellable'));
        }

        if ($product->stock_balance < $qty) {
            abort(422, __('pos.insufficient_stock'));
        }

        $unitPrice = $pricing->priceFor($product);

        return [
            'product' => $product,
            'product_id' => $product->id,
            'service_id' => null,
            'name' => $product->name,
            'unit_price' => $unitPrice,
            'qty' => $qty,
            'subtotal' => $unitPrice * $qty,
        ];
    }

    private function serviceLine(int $serviceId, int $qty, PromoPricing $pricing): array
    {
        $service = Service::findOrFail($serviceId);

        $unitPrice = $pricing->priceFor($service);

        return [
            'product' => null,
            'product_id' => null,
            'service_id' => $service->id,
            'name' => $service->name,
            'unit_price' => $unitPrice,
            'qty' => $qty,
            'subtotal' => $unitPrice * $qty,
        ];
    }
}
