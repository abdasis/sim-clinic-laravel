<?php

namespace Tests\Feature\Broadcast;

use App\Enums\ClinicRole;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Kelola template resmi Meta dari dalam aplikasi: lihat semua status,
 * daftarkan baru dengan contoh isian, dan hapus.
 */
class BroadcastTemplateManageTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private const WABA_ID = '112233445566';

    private function cloudConfigured(?string $wabaId = self::WABA_ID): void
    {
        config([
            'meta_whatsapp.access_token' => 'token-uji',
            'meta_whatsapp.api_version' => 'v21.0',
            'meta_whatsapp.base_url' => 'https://graph.test',
        ]);

        WhatsappSetting::create([
            'tenant_id' => $this->tenant->id,
            'phone_number_id' => '109876543210',
            'waba_id' => $wabaId,
        ]);
    }

    /** @return array<string, mixed> */
    private function template(array $overrides = []): array
    {
        return array_replace([
            'name' => 'promo_bulanan',
            'language' => 'id',
            'category' => 'MARKETING',
            'header_type' => 'TEXT',
            'header_text' => 'Promo Klinik',
            'body' => 'Halo {{1}}, kabar dari {{2}}.',
        ], $overrides);
    }

    public function test_all_template_statuses_are_listed(): void
    {
        $this->actingAsClinicUser();
        $this->cloudConfigured();

        Http::fake([
            'graph.test/*' => Http::response(['data' => [
                [
                    'id' => 'hsm-1',
                    'name' => 'promo_bulanan',
                    'status' => 'APPROVED',
                    'language' => 'id',
                    'category' => 'MARKETING',
                    'components' => [
                        ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Promo'],
                        ['type' => 'BODY', 'text' => 'Halo {{1}}, promo {{2}}'],
                    ],
                ],
                [
                    'id' => 'hsm-2',
                    'name' => 'pengingat',
                    'status' => 'PENDING',
                    'language' => 'id',
                    'category' => 'UTILITY',
                    'components' => [['type' => 'BODY', 'text' => 'Sampai jumpa']],
                ],
                [
                    'id' => 'hsm-3',
                    'name' => 'ditolak',
                    'status' => 'REJECTED',
                    'rejected_reason' => 'Nama merek tidak jelas',
                    'language' => 'id',
                    'category' => 'MARKETING',
                    'components' => [['type' => 'BODY', 'text' => 'Promo']],
                ],
            ]], 200),
        ]);

        $this->getJson($this->tenantUrl('broadcasts/templates/manage'))
            ->assertOk()
            ->assertJsonPath('meta.ready', true)
            ->assertJsonPath('data.0.id', 'hsm-1')
            ->assertJsonPath('data.0.status', 'APPROVED')
            ->assertJsonPath('data.0.header_text', 'Promo')
            ->assertJsonPath('data.1.status', 'PENDING')
            ->assertJsonPath('data.2.status', 'REJECTED')
            ->assertJsonPath('data.2.rejected_reason', 'Nama merek tidak jelas');

        // Daftar kelola sengaja tanpa filter status.
        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/message_templates')) {
                return false;
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ! array_key_exists('status', $query)
                && str_contains((string) ($query['fields'] ?? ''), 'rejected_reason');
        });
    }

    public function test_a_template_is_created_with_components_and_examples(): void
    {
        $this->actingAsClinicUser();
        $this->cloudConfigured();

        Http::fake([
            'graph.test/*' => Http::response(['id' => 'hsm-9', 'status' => 'PENDING'], 200),
        ]);

        $this->postJson($this->tenantUrl('broadcasts/templates'), $this->template())
            ->assertStatus(201)
            ->assertJsonPath('data.id', 'hsm-9')
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.header_type', 'TEXT')
            ->assertJsonPath('data.header_text', 'Promo Klinik')
            ->assertJsonPath('data.body_params_count', 2)
            ->assertJsonPath('meta.message', __('broadcast.template_created'));

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), '/message_templates')) {
                return false;
            }

            $data = $request->data();

            return $data['name'] === 'promo_bulanan'
                && $data['language'] === 'id'
                && $data['category'] === 'MARKETING'
                && $data['components'][0] === [
                    'type' => 'HEADER',
                    'format' => 'TEXT',
                    'text' => 'Promo Klinik',
                ]
                && $data['components'][1]['type'] === 'BODY'
                && $data['components'][1]['example']['body_text'] === [['Budi', 'Klinik klinik-uji']];
        });
    }

    public function test_the_template_form_rejects_invalid_name_category_and_header(): void
    {
        $this->actingAsClinicUser();
        $this->cloudConfigured();

        Http::fake();

        $this->postJson($this->tenantUrl('broadcasts/templates'), $this->template(['name' => 'Promo Bulanan']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->postJson($this->tenantUrl('broadcasts/templates'), $this->template(['category' => 'PROMO']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');

        $this->postJson($this->tenantUrl('broadcasts/templates'), $this->template(['header_text' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('header_text');

        Http::assertNothingSent();
    }

    public function test_deleting_a_template_sends_its_name_and_hsm_id(): void
    {
        $this->actingAsClinicUser();
        $this->cloudConfigured();

        Http::fake([
            'graph.test/*' => Http::response(['success' => true], 200),
        ]);

        $this->deleteJson($this->tenantUrl('broadcasts/templates/promo_bulanan?hsm_id=hsm-9'))
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.message', __('broadcast.template_deleted'));

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_contains($request->url(), 'name=promo_bulanan')
            && str_contains($request->url(), 'hsm_id=hsm-9'));
    }

    public function test_the_manage_list_is_not_ready_when_waba_is_missing(): void
    {
        $this->actingAsClinicUser();
        $this->cloudConfigured(null);

        $this->getJson($this->tenantUrl('broadcasts/templates/manage'))
            ->assertOk()
            ->assertJsonPath('meta.ready', false)
            ->assertJsonPath('data', []);
    }

    public function test_the_cache_is_forgotten_after_create_and_delete(): void
    {
        $this->actingAsClinicUser();
        $this->cloudConfigured();

        $listCalls = 0;

        Http::fake(function (Request $request) use (&$listCalls) {
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'hsm-baru', 'status' => 'PENDING'], 200);
            }

            if ($request->method() === 'DELETE') {
                return Http::response(['success' => true], 200);
            }

            $listCalls++;

            return Http::response(['data' => [[
                'id' => 'hsm-'.$listCalls,
                'name' => 'template_'.$listCalls,
                'status' => 'APPROVED',
                'language' => 'id',
                'category' => 'UTILITY',
                'components' => [['type' => 'BODY', 'text' => 'Halo']],
            ]]], 200);
        });

        $this->getJson($this->tenantUrl('broadcasts/templates/manage'))
            ->assertJsonPath('data.0.name', 'template_1');

        $this->postJson($this->tenantUrl('broadcasts/templates'), $this->template())->assertStatus(201);

        $this->getJson($this->tenantUrl('broadcasts/templates/manage'))
            ->assertJsonPath('data.0.name', 'template_2');

        $this->deleteJson($this->tenantUrl('broadcasts/templates/template_2'))->assertOk();

        $this->getJson($this->tenantUrl('broadcasts/templates/manage'))
            ->assertJsonPath('data.0.name', 'template_3');
    }

    public function test_a_cashier_cannot_manage_templates(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $this->cloudConfigured();

        Http::fake();

        $this->getJson($this->tenantUrl('broadcasts/templates/manage'))->assertForbidden();
        $this->postJson($this->tenantUrl('broadcasts/templates'), $this->template())->assertForbidden();
        $this->deleteJson($this->tenantUrl('broadcasts/templates/promo_bulanan'))->assertForbidden();

        Http::assertNothingSent();
    }
}
