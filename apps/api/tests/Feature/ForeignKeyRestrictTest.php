<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\MedicalPhoto;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\TreatmentRecord;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Master data yang sudah dipakai riwayat tidak boleh bisa dihapus permanen —
 * penghapusan harus lewat arsip atau nonaktifkan.
 *
 * Migration FK dilewati di SQLite, jadi test ini hanya bermakna di
 * PostgreSQL dan otomatis dilewati pada driver lain.
 */
class ForeignKeyRestrictTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (\DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('FK RESTRICT hanya ditegakkan di PostgreSQL.');
        }

        $this->actingAsClinicUser();
    }

    public function test_patient_with_booking_cannot_be_force_deleted(): void
    {
        $booking = $this->makeBooking();

        $this->expectException(QueryException::class);

        Patient::withTrashed()->find($booking->patient_id)->forceDelete();
    }

    public function test_service_with_booking_cannot_be_deleted(): void
    {
        $booking = $this->makeBooking();

        $this->expectException(QueryException::class);

        Service::find($booking->service_id)->delete();
    }

    public function test_staff_with_booking_cannot_be_force_deleted(): void
    {
        $booking = $this->makeBooking();

        $this->expectException(QueryException::class);

        User::withTrashed()->find($booking->assignee_id)->forceDelete();
    }

    public function test_product_with_stock_movement_cannot_be_deleted(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 5,
            'balance_after' => 5,
            'note' => 'Stok awal',
        ]);

        $this->expectException(QueryException::class);

        $product->delete();
    }

    public function test_patient_with_medical_record_cannot_be_force_deleted(): void
    {
        $record = $this->makeMedicalRecord();

        $this->expectException(QueryException::class);

        Patient::withTrashed()->find($record->patient_id)->forceDelete();
    }

    public function test_booking_with_medical_record_cannot_be_deleted(): void
    {
        $record = $this->makeMedicalRecord();

        $this->expectException(QueryException::class);

        Booking::find($record->booking_id)->delete();
    }

    public function test_medical_record_with_treatment_cannot_be_force_deleted(): void
    {
        $record = $this->makeMedicalRecord();
        TreatmentRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'medical_record_id' => $record->id,
        ]);

        $this->expectException(QueryException::class);

        $record->forceDelete();
    }

    public function test_medical_record_with_photo_cannot_be_force_deleted(): void
    {
        $record = $this->makeMedicalRecord();
        MedicalPhoto::factory()->create([
            'tenant_id' => $this->tenant->id,
            'medical_record_id' => $record->id,
        ]);

        $this->expectException(QueryException::class);

        $record->forceDelete();
    }

    public function test_medical_record_soft_delete_keeps_its_children(): void
    {
        $record = $this->makeMedicalRecord();
        $treatment = TreatmentRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'medical_record_id' => $record->id,
        ]);

        $record->delete();

        $this->assertSoftDeleted('medical_records', ['id' => $record->id]);
        $this->assertDatabaseHas('treatment_records', ['id' => $treatment->id]);
    }

    public function test_unreferenced_master_data_can_still_be_deleted(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $product->delete();
        $patient->forceDelete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('patients', ['id' => $patient->id]);
    }

    public function test_soft_delete_is_not_blocked_by_restrict(): void
    {
        $booking = $this->makeBooking();
        $patient = Patient::find($booking->patient_id);

        $patient->delete();

        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    private function makeMedicalRecord(): MedicalRecord
    {
        $booking = $this->makeBooking();

        return MedicalRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'patient_id' => $booking->patient_id,
            'author_id' => auth()->id(),
        ]);
    }

    private function makeBooking(): Booking
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Facial',
            'price' => 100000,
            'status' => 'active',
        ]);

        return Booking::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'assignee_id' => auth()->id(),
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => BookingStatus::Pending,
        ]);
    }
}
