<?php

namespace Tests\Feature\Transaction;

use App\Enums\BookingStatus;
use App\Enums\ClinicRole;
use App\Models\Booking;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_cashier_can_record_transaction_with_snapshot(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = $this->makeService();

        $response = $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['service_id' => $service->id, 'qty' => 2]],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.subtotal', '200000.00');
        $response->assertJsonPath('data.paid_amount', '0.00');
        $response->assertJsonPath('data.payment_status', 'unpaid');
        $response->assertJsonPath('data.items.0.name', 'Facial');

        // Snapshot harus bertahan walau master layanan berubah kemudian.
        $service->update(['name' => 'Nama Diubah', 'price' => 999]);

        $this->getJson($this->tenantUrl('transactions/'.$response->json('data.id')))
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Facial');
    }

    public function test_patient_is_required(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $service = $this->makeService();

        $this->postJson($this->tenantUrl('transactions'), [
            'items' => [['service_id' => $service->id, 'qty' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');
    }

    public function test_item_cannot_be_both_service_and_product(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = $this->makeService();
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [[
                'service_id' => $service->id,
                'product_id' => $product->id,
                'qty' => 1,
            ]],
        ])->assertStatus(422);
    }

    public function test_unfinished_booking_cannot_be_billed(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = $this->makeService();

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'assignee_id' => auth()->id(),
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => BookingStatus::Pending,
        ]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'booking_id' => $booking->id,
            'items' => [['service_id' => $service->id, 'qty' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('booking_id');
    }

    /**
     * Kunjungan yang sama menjadi titik temu rekam medis dan tagihannya:
     * rekam medis menunjuk booking, transaksi menunjuk booking yang sama.
     * Tanpa tautan ini keduanya berjalan sendiri-sendiri dan tidak ada cara
     * menelusuri tindakan mana yang sudah dibayar.
     */
    public function test_finished_booking_can_be_billed_and_stays_linked(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = $this->makeService();

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'assignee_id' => auth()->id(),
            'start_at' => now()->subDay(),
            'end_at' => now()->subDay()->addHour(),
            'status' => BookingStatus::Done,
        ]);

        $response = $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'booking_id' => $booking->id,
            'items' => [['service_id' => $service->id, 'qty' => 1]],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('transactions', [
            'id' => $response->json('data.id'),
            'booking_id' => $booking->id,
            'patient_id' => $patient->id,
        ]);
    }

    public function test_transaction_of_other_tenant_is_not_reachable(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $other = $this->createTenant('klinik-lain');

        $foreign = Transaction::factory()->create([
            'tenant_id' => $other->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $other->id])->id,
            'cashier_id' => auth()->id(),
        ]);

        // createTenant memindahkan tenant aktif; kembalikan ke tenant penguji.
        app()->instance('tenant', $this->tenant);

        $this->getJson($this->tenantUrl('transactions/'.$foreign->id))->assertNotFound();
        $this->deleteJson($this->tenantUrl('transactions/'.$foreign->id))->assertNotFound();

        $this->assertNull($foreign->fresh()->deleted_at);
    }

    public function test_destroy_endpoint_soft_deletes_transaction(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $transaction = Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'cashier_id' => auth()->id(),
        ]);

        $this->deleteJson($this->tenantUrl('transactions/'.$transaction->id))->assertOk();

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);

        $this->getJson($this->tenantUrl('transactions'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    private function makeService(): Service
    {
        return Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial',
            'price' => 100000,
            'status' => 'active',
        ]);
    }
}
