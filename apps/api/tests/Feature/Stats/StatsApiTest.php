<?php

namespace Tests\Feature\Stats;

use App\Enums\ClinicRole;
use App\Enums\StatsModule;
use App\Enums\StockMovementType;
use App\Models\Patient;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Endpoint statistik memakai agregasi SQL mentah per modul, jadi yang diuji
 * di sini adalah bentuk balikan dan batas tenant — bukan angka per query.
 */
class StatsApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    public function test_every_module_returns_kpis_and_zero_filled_trend(): void
    {
        $this->actingAsClinicUser();

        foreach (StatsModule::cases() as $module) {
            $response = $this->getJson($this->tenantUrl('stats/'.$module->value));

            $response->assertOk();
            $response->assertJsonPath('meta.module', $module->value);
            $response->assertJsonPath('meta.days', 30);
            $response->assertJsonCount(30, 'data.trend');
            $response->assertJsonStructure([
                'data' => [
                    'kpis' => [['key', 'label', 'value', 'unit']],
                    'trend' => [['date', 'value']],
                    'trend_metric',
                    'trend_label',
                ],
            ]);
        }
    }

    public function test_trend_counts_only_the_active_tenant(): void
    {
        $this->actingAsClinicUser();

        $other = $this->createTenant('klinik-lain');
        Patient::factory()->count(3)->create(['tenant_id' => $other->id]);
        app()->instance('tenant', $this->tenant);

        $response = $this->getJson($this->tenantUrl('stats/patients'))->assertOk();

        $this->assertSame(0, $response->json('data.kpis.0.value'));
        $this->assertSame(0, array_sum(array_column($response->json('data.trend'), 'value')));
    }

    public function test_inventory_trend_nets_incoming_against_outgoing(): void
    {
        $this->actingAsClinicUser();
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        foreach ([[StockMovementType::In, 10], [StockMovementType::SoldPos, 4]] as [$type, $qty]) {
            StockMovement::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $qty,
                'balance_after' => 0,
            ]);
        }

        $response = $this->getJson($this->tenantUrl('stats/inventory'))->assertOk();
        $kpis = collect($response->json('data.kpis'))->keyBy('key');

        $this->assertSame(10, $kpis['total_in']['value']);
        $this->assertSame(4, $kpis['total_out']['value']);
        // Titik hari ini adalah selisihnya; hari lain tetap nol.
        $this->assertSame(6, array_sum(array_column($response->json('data.trend'), 'value')));
    }

    public function test_unknown_module_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->getJson($this->tenantUrl('stats/kucing'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('module');
    }

    public function test_module_outside_role_matrix_is_forbidden(): void
    {
        // Kasir tidak punya akses modul inventaris (R2 matriks).
        $this->actingAsClinicUser(ClinicRole::Cashier);

        $this->getJson($this->tenantUrl('stats/inventory'))->assertForbidden();
        $this->getJson($this->tenantUrl('stats/transactions'))->assertOk();
    }

    public function test_days_beyond_the_cap_is_rejected(): void
    {
        $this->actingAsClinicUser();

        $this->getJson($this->tenantUrl('stats/patients').'?days=365')
            ->assertStatus(422)
            ->assertJsonValidationErrors('days');
    }
}
