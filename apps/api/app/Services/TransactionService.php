<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\TransactionItem;
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
        return DB::transaction(function () use ($data): Transaction {
            $lines = $this->buildLines($data['items'] ?? []);
            $subtotal = array_sum(array_column($lines, 'subtotal'));

            $transaction = Transaction::create([
                'patient_id' => $data['patient_id'] ?? null,
                'booking_id' => $data['booking_id'] ?? null,
                'cashier_id' => Auth::id(),
                'invoice_number' => Transaction::generateInvoiceNumber(),
                'subtotal' => $subtotal,
                'payment_status' => PaymentStatus::Unpaid,
            ]);

            foreach ($lines as $line) {
                $transaction->items()->create([
                    'product_id' => $line['product_id'],
                    'service_id' => $line['service_id'],
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

            $transaction->invoice()->create(['issued_at' => now()]);

            return $transaction->load('items', 'patient');
        });
    }

    /**
     * Bangun baris item dari master (Service/Product) dengan snapshot nama+harga.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $qty = (int) $item['qty'];
            $lines[] = isset($item['product_id']) && $item['product_id'] !== null
                ? $this->productLine((int) $item['product_id'], $qty)
                : $this->serviceLine((int) $item['service_id'], $qty);
        }

        return $lines;
    }

    private function productLine(int $productId, int $qty): array
    {
        $product = Product::findOrFail($productId);

        if ($product->stock_balance < $qty) {
            abort(422, __('pos.insufficient_stock'));
        }

        return [
            'product' => $product,
            'product_id' => $product->id,
            'service_id' => null,
            'name' => $product->name,
            'unit_price' => $product->price,
            'qty' => $qty,
            'subtotal' => (float) $product->price * $qty,
        ];
    }

    private function serviceLine(int $serviceId, int $qty): array
    {
        $service = Service::findOrFail($serviceId);

        return [
            'product' => null,
            'product_id' => null,
            'service_id' => $service->id,
            'name' => $service->name,
            'unit_price' => $service->price,
            'qty' => $qty,
            'subtotal' => (float) $service->price * $qty,
        ];
    }
}
