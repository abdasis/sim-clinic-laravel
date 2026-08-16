<?php

namespace Tests\Feature\Patient;

use App\Enums\ClinicRole;
use App\Enums\PaymentStatus;
use App\Models\Patient;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Hapus pasien. Yang dijaga: catatan medis dan nota tidak pernah ikut hilang
 * hanya karena daftar pasien dirapikan.
 */
class PatientDeleteTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function visited(Patient $patient): Transaction
    {
        return Transaction::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'cashier_id' => auth()->id(),
            'invoice_number' => 'INV-PASIEN-'.$patient->id,
            'subtotal' => 100_000,
            'paid_amount' => 100_000,
            'payment_status' => PaymentStatus::Paid,
            'issued_at' => now(),
        ]);
    }

    public function test_deletes_patient_who_never_visited(): void
    {
        $this->actingAsClinicUser();
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Salah Ketik',
        ]);

        $this->deleteJson($this->tenantUrl("patients/{$patient->id}"))
            ->assertOk()
            ->assertJsonPath('meta.deleted', true);

        // Benar-benar hilang, bukan sekadar disembunyikan.
        $this->assertDatabaseMissing('patients', ['id' => $patient->id]);
    }

    public function test_archives_patient_who_already_visited(): void
    {
        $this->actingAsClinicUser();
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sudah Datang',
        ]);
        $this->visited($patient);

        $this->deleteJson($this->tenantUrl("patients/{$patient->id}"))
            ->assertOk()
            ->assertJsonPath('meta.deleted', false);

        // Barisnya tetap ada, hanya diarsipkan — notanya masih menunjuk ke sana.
        $this->assertNotNull($patient->fresh()->deleted_at);
        $this->assertDatabaseHas('transactions', ['patient_id' => $patient->id]);
    }

    public function test_listing_marks_which_patients_may_be_deleted(): void
    {
        $this->actingAsClinicUser();

        $fresh = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Belum Datang',
        ]);
        $visited = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pernah Datang',
        ]);
        $this->visited($visited);

        $rows = collect(
            $this->getJson($this->tenantUrl('patients?per_page=50'))->assertOk()->json('data')
        )->keyBy('name');

        $this->assertTrue($rows['Belum Datang']['can_delete']);
        $this->assertFalse($rows['Pernah Datang']['can_delete']);
        $this->assertSame($fresh->id, $rows['Belum Datang']['id']);
    }

    public function test_listing_sends_the_gender_label(): void
    {
        $this->actingAsClinicUser();

        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ani Wijaya',
            'gender' => 'female',
        ]);

        $row = collect($this->getJson($this->tenantUrl('patients?per_page=50'))->json('data'))
            ->firstWhere('name', 'Ani Wijaya');

        $this->assertSame('Perempuan', $row['gender_label']);
    }

    public function test_therapist_may_not_delete_patients(): void
    {
        $this->actingAsClinicUser();
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAsClinicUser(ClinicRole::Therapist);

        $this->deleteJson($this->tenantUrl("patients/{$patient->id}"))->assertForbidden();

        $this->assertDatabaseHas('patients', ['id' => $patient->id]);
    }

    public function test_updates_patient_details(): void
    {
        $this->actingAsClinicUser();
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Nama Lama',
            'phone' => '081200000009',
        ]);

        $this->putJson($this->tenantUrl("patients/{$patient->id}"), [
            'name' => 'Nama Baru',
            'phone' => '081200000010',
            'gender' => 'female',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.gender_label', 'Perempuan');

        $this->assertSame('081200000010', $patient->fresh()->phone);
    }
}
