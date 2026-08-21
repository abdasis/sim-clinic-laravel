<?php

namespace App\Services;

use App\Actions\LogAuditAction;
use App\Actions\Transaction\CancelTransactionAction;
use App\Actions\Transaction\SoftDeleteTransactionAction;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Support\PromoPricing;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
            // Tanggal boleh dimundurkan untuk mencatat penjualan yang
            // terlewat; server yang menolak tanggal di masa depan.
            $issuedAt = isset($data['issued_at'])
                ? Carbon::parse($data['issued_at'])
                : now();

            $lines = $this->buildLines($data['items'] ?? []);
            $subtotal = array_sum(array_column($lines, 'subtotal'));

            $transaction = Transaction::create([
                'patient_id' => $data['patient_id'],
                'booking_id' => $data['booking_id'] ?? null,
                'cashier_id' => Auth::id(),
                // Nomor diambil di dalam transaction agar barisnya terkunci.
                'invoice_number' => Transaction::generateInvoiceNumber($issuedAt),
                'subtotal' => $subtotal,
                'paid_amount' => 0,
                'payment_status' => PaymentStatus::Unpaid,
                'issued_at' => $issuedAt,
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
                    'list_price' => $line['list_price'],
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
     * Batalkan transaksi (FR-058). Stok produknya dikembalikan dan nilainya
     * berhenti dihitung sebagai pemasukan, tapi notanya tetap terlihat —
     * jejak kas tidak boleh hilang begitu saja.
     */
    public function cancel(Transaction $transaction): Transaction
    {
        return app(CancelTransactionAction::class)->handle($transaction);
    }

    /**
     * Buang transaksi dari daftar aktif tanpa menghapus jejaknya; nomor
     * notanya tetap terpakai supaya urutan invoice tidak bergeser.
     */
    public function softDelete(Transaction $transaction): Transaction
    {
        return app(SoftDeleteTransactionAction::class)->handle($transaction);
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

        // Master diambil sekali untuk seluruh keranjang. Sebelumnya tiap baris
        // menembak findOrFail sendiri, jadi satu transaksi berisi sepuluh item
        // berarti sepuluh kueri — dan promonya sepuluh kueri lagi.
        $collected = collect($items);
        $productIds = $collected
            ->filter(fn (array $item) => ($item['product_id'] ?? null) !== null)
            ->map(fn (array $item) => (int) $item['product_id'])
            ->unique();
        $serviceIds = $collected
            ->filter(fn (array $item) => ($item['product_id'] ?? null) === null)
            ->map(fn (array $item) => (int) $item['service_id'])
            ->unique();

        $products = Product::query()->whereKey($productIds)->get()->keyBy('id');
        $services = Service::query()->whereKey($serviceIds)->get()->keyBy('id');

        $pricing->preload($products->values()->merge($services->values()));

        $lines = [];

        foreach ($items as $item) {
            $qty = (int) $item['qty'];
            $line = ($item['product_id'] ?? null) !== null
                ? $this->productLine(
                    $this->mustFind($products, (int) $item['product_id'], Product::class),
                    $qty,
                    $pricing,
                )
                : $this->serviceLine(
                    $this->mustFind($services, (int) $item['service_id'], Service::class),
                    $qty,
                    $pricing,
                );

            $line['offered_by'] = $item['offered_by'] ?? null;
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Ambil satu master dari hasil muat massal, atau lempar galat yang sama
     * dengan findOrFail sebelumnya.
     *
     * Bentuk galatnya sengaja dipertahankan: id yang tidak ada tetap dijawab
     * 404, bukan 500, sama seperti saat tiap baris masih mencarinya sendiri.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $models
     * @param  class-string<TModel>  $class
     * @return TModel
     */
    private function mustFind(Collection $models, int $id, string $class)
    {
        $model = $models->get($id);

        if ($model === null) {
            throw (new ModelNotFoundException)->setModel($class, [$id]);
        }

        return $model;
    }

    private function productLine(Product $product, int $qty, PromoPricing $pricing): array
    {

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
            'list_price' => (float) $product->price,
            'unit_price' => $unitPrice,
            'qty' => $qty,
            'subtotal' => $unitPrice * $qty,
        ];
    }

    private function serviceLine(Service $service, int $qty, PromoPricing $pricing): array
    {
        $unitPrice = $pricing->priceFor($service);

        return [
            'product' => null,
            'product_id' => null,
            'service_id' => $service->id,
            'name' => $service->name,
            'list_price' => (float) $service->price,
            'unit_price' => $unitPrice,
            'qty' => $qty,
            'subtotal' => $unitPrice * $qty,
        ];
    }
}
