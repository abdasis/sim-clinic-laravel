<?php

namespace Tests\Unit\Actions\Patient;

use App\Actions\Patient\CreatePatientAction;
use App\Actions\Patient\UpdatePatientAction;
use App\Models\Activity;
use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientAuditLogTest extends TestCase
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

    public function test_create_logs_narrative_with_full_attributes(): void
    {
        app(CreatePatientAction::class)->handle([
            'name' => 'Bapak Andi',
            'phone' => '081200000001',
            'gender' => 'male',
        ]);

        $activity = Activity::where('event', 'patient.created')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('Membuat pasien Bapak Andi.', $activity->description);
        $this->assertSame('patient', $activity->log_name);
        $this->assertSame('Bapak Andi', $activity->properties['attributes']['name']);
        $this->assertSame($this->tenant->id, $activity->properties['tenant_id']);
    }

    public function test_update_logs_old_and_new_values(): void
    {
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Nama Lama',
        ]);

        app(UpdatePatientAction::class)->handle($patient, ['name' => 'Nama Baru']);

        $activity = Activity::where('event', 'patient.updated')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('Memperbarui pasien Nama Baru.', $activity->description);
        $this->assertSame('Nama Lama', $activity->properties['old']['name']);
        $this->assertSame('Nama Baru', $activity->properties['new']['name']);
    }
}
