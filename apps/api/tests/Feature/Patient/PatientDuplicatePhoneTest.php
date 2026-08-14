<?php

namespace Tests\Feature\Patient;

use App\Models\Patient;
use App\Models\Tenant;
use App\Services\PatientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientDuplicatePhoneTest extends TestCase
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

    public function test_duplicate_phone_warns_but_does_not_block(): void
    {
        $existing = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '081200000001',
        ]);

        [$patient, $duplicate] = app(PatientService::class)->create([
            'name' => 'Anak Pertama',
            'phone' => '081200000001',
            'gender' => 'female',
        ]);

        $this->assertNotNull($patient->id, 'Pasien tetap tersimpan meski nomor ganda.');
        $this->assertNotNull($duplicate);
        $this->assertSame($existing->id, $duplicate->id);
    }

    public function test_unique_phone_produces_no_warning(): void
    {
        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '081200000001',
        ]);

        [, $duplicate] = app(PatientService::class)->create([
            'name' => 'Pasien Lain',
            'phone' => '081200000002',
            'gender' => 'male',
        ]);

        $this->assertNull($duplicate);
    }

    public function test_same_phone_in_other_tenant_is_not_a_duplicate(): void
    {
        $other = Tenant::create([
            'name' => 'Klinik Lain',
            'slug' => 'klinik-lain',
            'phone' => '0812',
            'status' => 'active',
        ]);

        Patient::factory()->create([
            'tenant_id' => $other->id,
            'phone' => '081200000001',
        ]);

        [, $duplicate] = app(PatientService::class)->create([
            'name' => 'Pasien Tenant Ini',
            'phone' => '081200000001',
            'gender' => 'male',
        ]);

        $this->assertNull($duplicate, 'Nomor sama di tenant lain tidak boleh dianggap duplikat.');
    }
}
