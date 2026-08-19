<?php

namespace Tests\Feature\Service;

use App\Enums\ClinicRole;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Keluhan dari lapangan: sebuah layanan tetap ditolak dengan alasan "sudah
 * terjual" padahal seluruh transaksinya sudah dihapus.
 *
 * Penyebabnya, baris `transaction_items` tidak ikut hilang saat transaksinya
 * dihapus, dan pemeriksa jejak membacanya lewat query builder mentah yang
 * tidak tahu-menahu soal soft delete.
 */
class DeleteServiceAfterTransactionTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function soldService(): array
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $transaction = Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'cashier_id' => auth()->id(),
            'subtotal' => 100000,
        ]);

        $transaction->items()->create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $service->id,
            'name' => $service->name,
            'unit_price' => 100000,
            'qty' => 1,
            'subtotal' => 100000,
        ]);

        return [$service, $transaction];
    }

    /** Selama transaksinya masih ada, layanan memang tidak boleh hilang. */
    public function test_a_service_in_a_live_transaction_stays_protected(): void
    {
        $this->actingAsClinicUser();

        [$service] = $this->soldService();

        $this->deleteJson($this->tenantUrl('services/'.$service->id.'/force'))
            ->assertStatus(422);

        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    /**
     * Inti keluhannya: setelah transaksinya dihapus, layanan itu tidak lagi
     * terjual di mata sistem — tidak muncul di daftar, tidak dihitung di
     * laporan. Menahannya atas nama transaksi yang sudah tidak ada berarti
     * layanan itu tidak akan pernah bisa dihapus.
     */
    public function test_a_service_can_be_deleted_after_its_transaction_is_deleted(): void
    {
        $this->actingAsClinicUser();

        [$service, $transaction] = $this->soldService();

        $this->deleteJson($this->tenantUrl('transactions/'.$transaction->id))->assertOk();

        $this->deleteJson($this->tenantUrl('services/'.$service->id.'/force'))
            ->assertOk();

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    /**
     * Rekam medis juga bisa dihapus, dan catatan tindakannya ikut menahan
     * layanan dengan cara yang sama.
     */
    public function test_a_service_recorded_in_a_live_medical_record_stays_protected(): void
    {
        $this->actingAsClinicUser();

        [$service, $record] = $this->treatedService();

        $this->assertNotNull($record);

        $this->deleteJson($this->tenantUrl('services/'.$service->id.'/force'))
            ->assertStatus(422);
    }

    public function test_a_service_can_be_deleted_after_its_medical_record_is_deleted(): void
    {
        $this->actingAsClinicUser();

        [$service, $record] = $this->treatedService();

        $record->delete();

        $this->deleteJson($this->tenantUrl('services/'.$service->id.'/force'))
            ->assertOk();

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    /** @return array{0: Service, 1: MedicalRecord} */
    private function treatedService(): array
    {
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $booking = Booking::factory()->done()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'assignee_id' => auth()->id(),
        ]);

        $record = MedicalRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'booking_id' => $booking->id,
            'author_id' => auth()->id(),
        ]);

        $record->treatmentRecords()->create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
        ]);

        return [$service, $record];
    }

    /** Hal yang sama berlaku untuk produk. */
    public function test_a_product_can_be_deleted_after_its_transaction_is_deleted(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $transaction = Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'cashier_id' => auth()->id(),
            'subtotal' => 100000,
        ]);

        $transaction->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 100000,
            'qty' => 1,
            'subtotal' => 100000,
        ]);

        // Transaksi berisi produk harus dibatalkan dulu sebelum dihapus.
        $this->postJson($this->tenantUrl('transactions/'.$transaction->id.'/cancel'))->assertOk();
        $this->deleteJson($this->tenantUrl('transactions/'.$transaction->id))->assertOk();

        $this->deleteJson($this->tenantUrl('products/'.$product->id.'/force'))->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
