<?php

namespace Tests\Feature\Patient;

use App\Enums\ClinicRole;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class PatientApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_admin_can_create_patient(): void
    {
        $this->actingAsClinicUser();

        $response = $this->postJson($this->tenantUrl('patients'), [
            'name' => 'Ibu Sinta',
            'whatsapp' => '081200000001',
            'gender' => 'female',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Ibu Sinta');
        $response->assertJsonPath('data.deleted_at', null);
    }

    public function test_validation_rejects_empty_name(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('patients'), ['whatsapp' => '0812', 'gender' => 'female'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_deactivated_patient_disappears_from_list(): void
    {
        $this->actingAsClinicUser();
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->deleteJson($this->tenantUrl('patients/'.$patient->id))->assertOk();

        $this->getJson($this->tenantUrl('patients'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_therapist_cannot_create_patient(): void
    {
        // Matriks peran memberi therapist akses baca saja pada modul pasien.
        $this->actingAsClinicUser(ClinicRole::Therapist);

        $this->postJson($this->tenantUrl('patients'), [
            'name' => 'Pasien Baru',
            'whatsapp' => '081200000002',
            'gender' => 'male',
        ])->assertForbidden();

        $this->getJson($this->tenantUrl('patients'))->assertOk();
    }

    public function test_patient_of_other_tenant_is_not_reachable(): void
    {
        $this->actingAsClinicUser();

        $otherTenant = $this->createTenant('klinik-lain');
        $foreign = Patient::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->getJson($this->tenantUrl('patients/'.$foreign->id))->assertNotFound();

        $this->getJson($this->tenantUrl('patients'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_update_warns_about_duplicate_whatsapp(): void
    {
        $this->actingAsClinicUser();

        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'whatsapp' => '081200000001',
        ]);
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'whatsapp' => '081200000009',
        ]);

        $this->putJson($this->tenantUrl('patients/'.$patient->id), [
            'name' => $patient->name,
            'whatsapp' => '081200000001',
            'gender' => 'female',
        ])
            ->assertOk()
            ->assertJsonPath('meta.duplicate_warning', true);
    }
}
