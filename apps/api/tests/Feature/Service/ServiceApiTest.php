<?php

namespace Tests\Feature\Service;

use App\Enums\ClinicRole;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /** Payload persis seperti yang dikirim ServiceFormDialog. */
    private function formPayload(array $override = []): array
    {
        return array_merge([
            'name' => 'Facial Basic',
            'description' => '',
            'price' => 250000,
            'duration_minutes' => 30,
            'status' => 'active',
        ], $override);
    }

    public function test_admin_can_create_service_with_form_payload(): void
    {
        $this->actingAsClinicUser();

        $response = $this->postJson($this->tenantUrl('services'), $this->formPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Facial Basic');
        // Status datang dari default kolom; responsnya pernah mengirim null
        // sehingga baris yang baru dibuat tampil tanpa lencana status.
        $response->assertJsonPath('data.status', 'active');
        $this->assertDatabaseCount('services', 1);
    }

    public function test_created_service_appears_in_the_list(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('services'), $this->formPayload())->assertCreated();

        $this->getJson($this->tenantUrl('services'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_price_sent_as_string_is_accepted(): void
    {
        // Input number di HTML tetap bisa mengirim string dari beberapa jalur.
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('services'), $this->formPayload(['price' => '250000']))
            ->assertCreated();
    }

    public function test_description_may_be_omitted_or_null(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('services'), $this->formPayload(['description' => null]))
            ->assertCreated();
    }

    public function test_validation_rejects_empty_name(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('services'), $this->formPayload(['name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_doctor_cannot_create_service(): void
    {
        // Matriks peran memberi dokter akses baca saja pada katalog layanan.
        $this->actingAsClinicUser(ClinicRole::Doctor);

        $this->postJson($this->tenantUrl('services'), $this->formPayload())
            ->assertForbidden();
    }

    public function test_denial_is_explained_in_indonesian(): void
    {
        // Bawaan Laravel menjawab "This action is unauthorized." — tidak
        // membantu staf klinik yang cuma ingin tahu kenapa simpannya gagal.
        $this->actingAsClinicUser(ClinicRole::Doctor);

        $this->postJson($this->tenantUrl('services'), $this->formPayload())
            ->assertForbidden()
            ->assertJsonPath('message', __('clinic.forbidden'));
    }

    public function test_service_is_scoped_to_its_tenant(): void
    {
        $this->actingAsClinicUser();
        $other = $this->createTenant('klinik-lain');
        Service::factory()->create(['tenant_id' => $other->id]);
        app()->instance('tenant', $this->tenant);

        $this->getJson($this->tenantUrl('services'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
