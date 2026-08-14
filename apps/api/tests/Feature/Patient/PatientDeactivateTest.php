<?php

namespace Tests\Feature\Patient;

use App\Actions\Patient\DeactivatePatientAction;
use App\Models\Activity;
use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientDeactivateTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Klinik Uji',
            'slug' => 'klinik-uji',
            'phone' => '0811',
            'status' => 'active',
        ]);

        app()->instance('tenant', $this->tenant);
    }

    public function test_deactivate_soft_deletes_patient(): void
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        app(DeactivatePatientAction::class)->handle($patient);

        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    }

    public function test_deactivated_patient_hidden_from_active_list(): void
    {
        Patient::factory()->create(['tenant_id' => $this->tenant->id]);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        app(DeactivatePatientAction::class)->handle($patient);

        $this->assertSame(1, Patient::query()->count());
        $this->assertSame(2, Patient::withTrashed()->count());
    }

    public function test_deactivate_records_narrative_audit_log_with_old_and_new(): void
    {
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ibu Rina',
        ]);

        app(DeactivatePatientAction::class)->handle($patient);

        $activity = Activity::where('event', 'patient.deactivated')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('Menonaktifkan pasien Ibu Rina.', $activity->description);
        $this->assertNull($activity->properties['old']['deleted_at']);
        $this->assertNotNull($activity->properties['new']['deleted_at']);
        $this->assertSame($this->tenant->id, $activity->properties['tenant_id']);
    }

    public function test_history_survives_deactivation(): void
    {
        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        app(DeactivatePatientAction::class)->handle($patient);

        $this->assertDatabaseHas('patients', ['id' => $patient->id]);
        $this->assertNotNull(Patient::withTrashed()->find($patient->id));
    }
}
