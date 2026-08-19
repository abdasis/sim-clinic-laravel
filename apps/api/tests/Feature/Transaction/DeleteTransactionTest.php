<?php

namespace Tests\Feature\Transaction;

use App\Enums\ClinicRole;
use App\Enums\PaymentStatus;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Menghapus transaksi menyembunyikan barisnya dari seluruh kueri, termasuk
 * laporan. Untuk transaksi yang sudah dibayar itu berarti pemasukannya lenyap
 * tanpa jejak di laporan; untuk transaksi berisi produk, stoknya tetap
 * terpotong padahal penjualannya sudah tidak ada.
 *
 * Karena itu keduanya harus dibatalkan lebih dulu — pembatalan yang
 * mengembalikan stok dan mengeluarkan nilainya dari pemasukan.
 */
class DeleteTransactionTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function transaction(array $attributes = []): Transaction
    {
        return Transaction::factory()->create($attributes + [
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'cashier_id' => auth()->id(),
            'subtotal' => 100000,
        ]);
    }

    private function removeTransaction(Transaction $transaction)
    {
        return $this->deleteJson($this->tenantUrl('transactions/'.$transaction->id));
    }

    public function test_an_untouched_transaction_can_be_deleted(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $transaction = $this->transaction();

        $this->removeTransaction($transaction)->assertOk();

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }

    /** Transaksi berisi layanan saja tidak menyentuh stok, jadi aman dihapus. */
    public function test_a_service_only_transaction_can_be_deleted(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $transaction = $this->transaction();
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $transaction->items()->create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $service->id,
            'name' => $service->name,
            'unit_price' => 100000,
            'qty' => 1,
            'subtotal' => 100000,
        ]);

        $this->removeTransaction($transaction)->assertOk();

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }

    /**
     * Menghapus transaksi berbayar akan mengurangi pemasukan di laporan tanpa
     * ada yang mencatat ke mana perginya.
     */
    public function test_a_paid_transaction_must_be_cancelled_first(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $transaction = $this->transaction([
            'paid_amount' => 100000,
            'payment_status' => PaymentStatus::Paid,
        ]);

        $this->removeTransaction($transaction)
            ->assertStatus(422)
            ->assertJsonPath('message', __('pos.delete_requires_cancel'));

        $this->assertNull($transaction->fresh()->deleted_at);
    }

    /**
     * Stok hanya kembali lewat pembatalan. Menghapus transaksinya saja
     * meninggalkan stok terpotong untuk barang yang tidak lagi tercatat
     * terjual.
     */
    public function test_a_transaction_holding_products_must_be_cancelled_first(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $transaction = $this->transaction();
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $transaction->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 100000,
            'qty' => 1,
            'subtotal' => 100000,
        ]);

        $this->removeTransaction($transaction)->assertStatus(422);

        $this->assertNull($transaction->fresh()->deleted_at);
    }

    /** Setelah dibatalkan, keduanya sudah beres — hapus jadi boleh. */
    public function test_a_cancelled_transaction_can_be_deleted(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $transaction = $this->transaction([
            'paid_amount' => 100000,
            'payment_status' => PaymentStatus::Paid,
        ]);

        $this->postJson($this->tenantUrl('transactions/'.$transaction->id.'/cancel'))
            ->assertOk();

        $this->removeTransaction($transaction->fresh())->assertOk();

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }

    public function test_a_deleted_transaction_disappears_from_the_list(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->removeTransaction($this->transaction())->assertOk();

        $this->getJson($this->tenantUrl('transactions'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    /** Nomor notanya tidak dipakai ulang: barisnya masih ada di basis data. */
    public function test_a_deleted_transaction_keeps_its_invoice_number(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $transaction = $this->transaction();
        $number = $transaction->invoice_number;

        $this->removeTransaction($transaction)->assertOk();

        $this->assertSame(
            1,
            Transaction::withTrashed()->where('invoice_number', $number)->count(),
        );
    }

    public function test_a_doctor_cannot_delete_a_transaction(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $transaction = $this->transaction();

        $this->actingAsClinicUser(ClinicRole::Doctor);

        $this->removeTransaction($transaction)->assertForbidden();

        $this->assertNull($transaction->fresh()->deleted_at);
    }

    public function test_the_deletion_is_written_to_the_audit_log(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $transaction = $this->transaction();

        $this->removeTransaction($transaction)->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'pos.transaction.deleted']);
    }
}
