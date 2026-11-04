<?php

namespace Tests\Feature\Whatsapp;

use App\Enums\ConversationChannel;
use App\Models\WhatsappGatewaySetting;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Skema kredensial gateway WhatsApp milik tiap klinik: tersimpan terenkripsi,
 * tidak pernah ikut terserialisasi, dan cukup untuk memutuskan apakah klinik
 * sudah bisa mengirim lewat gateway.
 */
class GatewaySchemaTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    public function test_gateway_credentials_are_encrypted_and_hidden(): void
    {
        $setting = WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'gateway_tenant_key' => 'tenant-key-uji',
            'gateway_api_key' => 'api-key-uji',
            'gateway_webhook_secret' => 'secret-uji',
            'gateway_webhook_token' => 'token-uji',
            'gateway_session_id' => '11111111-1111-1111-1111-111111111111',
        ]);

        $raw = DB::table('whatsapp_settings')->where('id', $setting->id)->first();
        $this->assertNotSame('api-key-uji', $raw->gateway_api_key);
        $this->assertNotSame('secret-uji', $raw->gateway_webhook_secret);
        $this->assertTrue($setting->provisioned());
        $this->assertTrue($setting->isConfigured());
        $this->assertArrayNotHasKey('gateway_api_key', $setting->toArray());
    }

    /**
     * Token webhook dipakai sebagai bagian URL yang dicari tanpa scope tenant,
     * jadi harus tersimpan apa adanya; sisanya kredensial.
     */
    public function test_webhook_token_is_searchable_while_other_credentials_are_not(): void
    {
        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'session' => 'klinik-uji',
            'gateway_webhook_token' => 'token-cari',
        ]);

        $this->assertSame(
            'token-cari',
            DB::table('whatsapp_settings')->value('gateway_webhook_token'),
        );
    }

    public function test_session_id_alone_is_not_enough_to_count_as_configured(): void
    {
        $setting = WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'gateway_session_id' => '11111111-1111-1111-1111-111111111111',
        ]);

        $this->assertFalse($setting->isConfigured());
        $this->assertFalse($setting->provisioned());
    }

    public function test_gateway_server_and_send_attempt_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('whatsapp_gateway_settings', ['id', 'base_url']));

        $attempt = collect(Schema::getColumns('broadcast_recipients'))
            ->firstWhere('name', 'send_attempt');

        $this->assertNotNull($attempt);
        // SQLite mengembalikan default sebagai literal ber-kutip, PostgreSQL
        // sebagai angka; int sama-sama nol.
        $this->assertSame(0, (int) $attempt['default']);
    }

    public function test_gateway_channel_is_a_valid_conversation_channel(): void
    {
        $this->assertSame('gateway', ConversationChannel::Gateway->value);
        $this->assertSame('Gateway internal', ConversationChannel::Gateway->label());
    }
}
