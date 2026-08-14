<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Data satu tenant tidak boleh bisa diambil dari tenant lain hanya dengan
 * menebak ID di URL. Ini bergantung pada TenantScope yang aktif sebelum
 * route model binding berjalan.
 */
class TenantIsolationTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_service_of_other_tenant_returns_not_found(): void
    {
        $this->actingAsClinicUser();
        $other = $this->createTenant('klinik-lain');

        $foreign = Service::create([
            'tenant_id' => $other->id,
            'name' => 'Layanan Tetangga',
            'price' => 100000,
            'status' => 'active',
        ]);

        $this->getJson($this->tenantUrl('services/'.$foreign->id))->assertNotFound();
    }

    public function test_product_of_other_tenant_returns_not_found(): void
    {
        $this->actingAsClinicUser();
        $other = $this->createTenant('klinik-lain');

        $foreign = Product::factory()->create(['tenant_id' => $other->id]);

        $this->getJson($this->tenantUrl('products/'.$foreign->id))->assertNotFound();
    }

    public function test_patient_of_other_tenant_cannot_be_updated(): void
    {
        $this->actingAsClinicUser();
        $other = $this->createTenant('klinik-lain');

        $foreign = Patient::factory()->create(['tenant_id' => $other->id]);

        $this->putJson($this->tenantUrl('patients/'.$foreign->id), [
            'name' => 'Dibajak',
            'phone' => '081200000001',
            'gender' => 'male',
        ])->assertNotFound();

        $this->assertNotSame('Dibajak', $foreign->fresh()->name);
    }

    public function test_patient_of_other_tenant_cannot_be_deactivated(): void
    {
        $this->actingAsClinicUser();
        $other = $this->createTenant('klinik-lain');

        $foreign = Patient::factory()->create(['tenant_id' => $other->id]);

        $this->deleteJson($this->tenantUrl('patients/'.$foreign->id))->assertNotFound();

        $this->assertNull($foreign->fresh()->deleted_at);
    }
}
