<?php

namespace Tests\Feature\Chatbot;

use App\Enums\ClinicRole;
use App\Models\ChatbotSetting;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class ChatbotSettingApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /** Belum pernah disimpan tetap harus punya bentuk yang sama. */
    public function test_settings_have_a_shape_before_they_are_ever_saved(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->getJson($this->tenantUrl('chatbot/settings'))
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.agent_name', null)
            ->assertJsonPath('data.bookable_service_ids', []);
    }

    public function test_admin_can_save_settings(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->putJson($this->tenantUrl('chatbot/settings'), [
            'is_active' => true,
            'agent_name' => 'Mira',
            'bookable_service_ids' => [$service->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.agent_name', 'Mira');

        $this->assertDatabaseHas('chatbot_settings', [
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'agent_name' => 'Mira',
        ]);
    }

    /** Menyimpan dua kali tidak boleh menghasilkan dua baris per klinik. */
    public function test_saving_twice_keeps_one_row(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->putJson($this->tenantUrl('chatbot/settings'), ['is_active' => true])->assertOk();
        $this->putJson($this->tenantUrl('chatbot/settings'), ['is_active' => false])->assertOk();

        $this->assertSame(1, ChatbotSetting::query()->count());
        $this->assertFalse(ChatbotSetting::query()->first()->is_active);
    }

    public function test_a_therapist_cannot_touch_the_settings(): void
    {
        $this->actingAsClinicUser(ClinicRole::Therapist);

        $this->getJson($this->tenantUrl('chatbot/settings'))->assertForbidden();
        $this->putJson($this->tenantUrl('chatbot/settings'), ['is_active' => true])->assertForbidden();
    }

    /** Layanan milik klinik lain tidak boleh bisa dipasang sebagai bookable. */
    public function test_a_service_from_another_clinic_is_rejected(): void
    {
        $other = $this->createTenant('klinik-lain');
        $foreign = Service::factory()->create(['tenant_id' => $other->id]);

        $this->tenant = $this->createTenant('klinik-ini');
        $this->actingAsClinicUser(ClinicRole::Admin, $this->tenant);

        $this->putJson($this->tenantUrl('chatbot/settings'), [
            'is_active' => true,
            'bookable_service_ids' => [$foreign->id],
        ])->assertStatus(422);
    }

    public function test_admin_can_upload_an_agent_avatar(): void
    {
        Storage::fake('public');
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->postJson($this->tenantUrl('chatbot/settings/avatar'), [
            'file' => UploadedFile::fake()->image('agen.png'),
        ])
            ->assertOk()
            ->assertJsonPath('data.agent_avatar_url', fn (?string $url) => is_string($url));

        $path = ChatbotSetting::query()->first()->agent_avatar_path;

        $this->assertNotNull($path);
        // Berkas tiap klinik harus terpisah; kalau tidak, avatar satu klinik
        // bisa tertimpa klinik lain yang mengunggah nama berkas yang sama.
        $this->assertStringStartsWith('chatbot/'.$this->tenant->id.'/avatar/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_pdf_is_not_accepted_as_an_avatar(): void
    {
        Storage::fake('public');
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->postJson($this->tenantUrl('chatbot/settings/avatar'), [
            'file' => UploadedFile::fake()->create('berkas.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);
    }
}
