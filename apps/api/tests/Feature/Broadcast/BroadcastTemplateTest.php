<?php

namespace Tests\Feature\Broadcast;

use App\Enums\ClinicRole;
use App\Models\Broadcast;
use App\Models\Patient;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Template resmi Meta: daftar yang boleh dipilih, dan penjagaan bentuk
 * parameternya sebelum ratusan pesan diantrekan.
 */
class BroadcastTemplateTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const WABA_ID = '112233445566';

    private function cloudConfigured(): void
    {
        config([
            'meta_whatsapp.access_token' => 'token-uji',
            'meta_whatsapp.api_version' => 'v21.0',
            'meta_whatsapp.base_url' => 'https://graph.test',
        ]);

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'phone_number_id' => '109876543210',
            'waba_id' => self::WABA_ID,
        ]);
    }

    /** @return array<string, mixed> */
    private function template(array $overrides = []): array
    {
        return array_replace([
            'name' => 'promo_bulanan',
            'language' => 'id',
            'body' => 'Halo {{1}}, kabar dari {{2}}.',
            'params' => ['nama', 'klinik'],
        ], $overrides);
    }

    public function test_approved_templates_are_listed_and_normalized(): void
    {
        $this->actingAsClinicUser();
        $this->cloudConfigured();

        Http::fake([
            'graph.test/*' => Http::response(['data' => [[
                'name' => 'promo_bulanan',
                'status' => 'APPROVED',
                'language' => 'id',
                'category' => 'MARKETING',
                'components' => [
                    ['type' => 'HEADER', 'format' => 'IMAGE'],
                    ['type' => 'BODY', 'text' => 'Halo {{1}}, promo {{2}}'],
                ],
            ]]], 200),
        ]);

        $this->getJson($this->tenantUrl('broadcasts/templates'))
            ->assertOk()
            ->assertJsonPath('meta.ready', true)
            ->assertJsonPath('data.0.name', 'promo_bulanan')
            ->assertJsonPath('data.0.language', 'id')
            ->assertJsonPath('data.0.category', 'MARKETING')
            ->assertJsonPath('data.0.body', 'Halo {{1}}, promo {{2}}')
            ->assertJsonPath('data.0.header_type', 'IMAGE')
            ->assertJsonPath('data.0.body_params_count', 2);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.test/v21.0/'.self::WABA_ID.'/message_templates')
            && $request['status'] === 'APPROVED');
    }

    public function test_the_template_list_is_empty_when_cloud_api_is_not_ready(): void
    {
        $this->actingAsClinicUser();

        $this->getJson($this->tenantUrl('broadcasts/templates'))
            ->assertOk()
            ->assertJsonPath('meta.ready', false)
            ->assertJsonPath('data', []);
    }

    public function test_a_cashier_cannot_list_templates(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->cloudConfigured();

        $this->getJson($this->tenantUrl('broadcasts/templates'))->assertForbidden();
    }

    /**
     * Jumlah placeholder harus sama dengan jumlah parameter. Tanpa ini,
     * penolakan Meta baru terlihat setelah seluruh antrean terbentuk.
     */
    public function test_the_parameter_count_must_match_the_placeholders(): void
    {
        $this->actingAsClinicUser();
        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'whatsapp' => '081111111111']);

        $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo',
            'template' => $this->template(['params' => ['nama']]),
            'audience' => 'all',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('template.params');

        $this->assertSame(0, Broadcast::query()->count());
    }

    public function test_a_template_with_an_image_header_requires_a_poster(): void
    {
        $this->actingAsClinicUser();
        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'whatsapp' => '081111111111']);

        $this->postJson($this->tenantUrl('broadcasts'), [
            'title' => 'Promo',
            'template' => $this->template(['header_type' => 'IMAGE']),
            'audience' => 'all',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_a_test_message_with_an_image_template_is_refused(): void
    {
        $this->actingAsClinicUser();
        $this->cloudConfigured();

        $this->postJson($this->tenantUrl('broadcasts/test-message'), [
            'phone' => '081234567890',
            'template' => $this->template(['header_type' => 'IMAGE']),
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('broadcast.template_test_image_unsupported'));

        Http::assertNothingSent();
    }
}
