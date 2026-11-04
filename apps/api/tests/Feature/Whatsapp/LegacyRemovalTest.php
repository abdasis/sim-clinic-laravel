<?php

namespace Tests\Feature\Whatsapp;

use App\Enums\ConversationChannel;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\WhatsappGatewaySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Jalur lama (WAHA dan Cloud API Meta) sudah dilepas sepenuhnya. Yang tersisa
 * hanya gateway internal: kliennya, route webhook-nya, dan satu tempat
 * pengaturan alamat servernya untuk seluruh klinik.
 */
class LegacyRemovalTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_legacy_clients_and_routes_are_gone(): void
    {
        $this->assertFileDoesNotExist(app_path('Support/WahaClient.php'));
        $this->assertFileDoesNotExist(app_path('Support/CloudApiClient.php'));
        $this->assertFileDoesNotExist(app_path('Models/WahaSetting.php'));
        $this->assertFileDoesNotExist(app_path('Services/WhatsappConnectionService.php'));
        $this->assertFileDoesNotExist(app_path('Services/WhatsappTemplateService.php'));

        $this->getJson('/api/whatsapp/meta/webhook')->assertNotFound();
    }

    /**
     * Kanal percakapan hanya menyimpan asal pesan masuk. Setelah gateway
     * menjadi satu-satunya jalur, nilai lama tidak boleh tersisa di enum —
     * kalau tidak, kode baru bisa diam-diam membuat percakapan berkanal mati.
     */
    public function test_conversation_channel_only_has_the_gateway_case(): void
    {
        $values = array_map(fn (ConversationChannel $channel): string => $channel->value, ConversationChannel::cases());

        $this->assertSame(['gateway'], $values);
    }

    public function test_platform_admin_reads_and_saves_the_gateway_address(): void
    {
        $this->tenant = $this->createTenant();
        Sanctum::actingAs($this->platformAdmin());

        $this->getJson('/api/central/whatsapp-gateway')
            ->assertOk()
            ->assertJsonPath('data.base_url', null);

        $this->putJson('/api/central/whatsapp-gateway', ['base_url' => 'https://gateway.test'])
            ->assertOk()
            ->assertJsonPath('data.base_url', 'https://gateway.test');

        $this->getJson('/api/central/whatsapp-gateway')
            ->assertOk()
            ->assertJsonPath('data.base_url', 'https://gateway.test');

        $this->assertSame('https://gateway.test', WhatsappGatewaySetting::query()->value('base_url'));
    }

    public function test_clinic_user_cannot_read_or_change_the_shared_gateway_address(): void
    {
        $this->actingAsClinicUser();

        $this->getJson('/api/central/whatsapp-gateway')->assertForbidden();
        $this->putJson('/api/central/whatsapp-gateway', ['base_url' => 'https://milik-sendiri.test'])
            ->assertForbidden();

        $this->assertNull(WhatsappGatewaySetting::query()->first());
    }

    public function test_gateway_address_must_be_a_url(): void
    {
        $this->tenant = $this->createTenant();
        Sanctum::actingAs($this->platformAdmin());

        $this->putJson('/api/central/whatsapp-gateway', ['base_url' => 'bukan-alamat'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('base_url');
    }

    private function platformAdmin(): User
    {
        return User::factory()->create([
            'name' => 'Admin Platform',
            'email' => 'platform@sim-clinic.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::PlatformAdmin,
            'status' => UserStatus::Active,
        ]);
    }
}
