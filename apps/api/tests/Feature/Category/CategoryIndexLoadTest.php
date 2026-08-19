<?php

namespace Tests\Feature\Category;

use App\Enums\ClinicRole;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Uji asap halaman kelola kategori: permintaan yang benar-benar dikirim
 * halaman itu harus dijawab, lengkap dengan hitungan pemakaian.
 */
class CategoryIndexLoadTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_index_answers_the_request_the_page_actually_sends(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        foreach (['Skinbooster', 'Facial & Peeling', 'Treatment Lainnya'] as $name) {
            Category::create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
                'type' => 'service',
                'status' => 'active',
            ]);
        }

        // Persis parameter yang dikirim halaman kelola kategori.
        $response = $this->getJson($this->tenantUrl(
            'categories?page=1&per_page=10&filter[type]=service&filter[status]=all'
        ));

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
        $this->assertNotNull($response->json('data.0.usage_count'));
    }
}
