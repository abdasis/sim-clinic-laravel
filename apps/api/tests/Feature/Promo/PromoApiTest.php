<?php

namespace Tests\Feature\Promo;

use App\Enums\ClinicRole;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Promo;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Promo menyentuh uang, jadi yang diuji bukan hanya CRUD-nya melainkan harga
 * yang benar-benar tersimpan di transaksi — termasuk saat promonya sudah
 * lewat tanggal atau dimatikan.
 */
class PromoApiTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function service(int $price = 100000): Service
    {
        return Service::factory()->create(['tenant_id' => $this->tenant->id, 'price' => $price]);
    }

    private function promoFor(Service|Product $target, array $attributes = []): Promo
    {
        $promo = Promo::factory()->create(['tenant_id' => $this->tenant->id] + $attributes);

        $promo->items()->create([
            'promotable_type' => $target->getMorphClass(),
            'promotable_id' => $target->id,
        ]);

        return $promo;
    }

    public function test_creates_promo_with_targets(): void
    {
        $this->actingAsClinicUser();
        $service = $this->service();

        $response = $this->postJson($this->tenantUrl('promos'), [
            'name' => 'Diskon Kemerdekaan',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addWeek()->toDateString(),
            'targets' => [['type' => 'service', 'id' => $service->id]],
        ])->assertCreated();

        $response->assertJsonPath('data.is_running', true);
        $this->assertSame(1, Promo::first()->items()->count());
    }

    public function test_rejects_end_date_before_start_date(): void
    {
        $this->actingAsClinicUser();
        $service = $this->service();

        $this->postJson($this->tenantUrl('promos'), [
            'name' => 'Promo Terbalik',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->subDay()->toDateString(),
            'targets' => [['type' => 'service', 'id' => $service->id]],
        ])->assertStatus(422)->assertJsonValidationErrors('ends_at');
    }

    public function test_rejects_percent_above_hundred(): void
    {
        $this->actingAsClinicUser();
        $service = $this->service();

        $this->postJson($this->tenantUrl('promos'), [
            'name' => 'Gratis Berlebihan',
            'discount_type' => 'percent',
            'discount_value' => 120,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDay()->toDateString(),
            'targets' => [['type' => 'service', 'id' => $service->id]],
        ])->assertStatus(422)->assertJsonValidationErrors('discount_value');
    }

    public function test_rejects_promo_without_targets(): void
    {
        $this->actingAsClinicUser();

        $this->postJson($this->tenantUrl('promos'), [
            'name' => 'Promo Kosong',
            'discount_type' => 'fixed',
            'discount_value' => 5000,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDay()->toDateString(),
            'targets' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('targets');
    }

    public function test_catalog_exposes_promo_price(): void
    {
        $this->actingAsClinicUser();
        $service = $this->service(100000);
        $this->promoFor($service, ['discount_type' => 'percent', 'discount_value' => 25]);

        $this->getJson($this->tenantUrl('services'))
            ->assertOk()
            ->assertJsonPath('data.0.promo.price', '75000.00')
            ->assertJsonPath('data.0.price', '100000.00');
    }

    public function test_transaction_uses_promo_price(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $service = $this->service(100000);
        $this->promoFor($service, ['discount_type' => 'percent', 'discount_value' => 25]);

        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['service_id' => $service->id, 'qty' => 2]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '150000.00')
            ->assertJsonPath('data.items.0.unit_price', '75000.00')
            // Harga normal ikut tersimpan supaya nota bisa menyebut
            // potongannya, bukan cuma harga akhir yang lebih murah.
            ->assertJsonPath('data.items.0.list_price', '100000.00');
    }

    public function test_expired_promo_does_not_change_price(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $service = $this->service(100000);
        $this->promoFor($service, [
            'discount_type' => 'percent',
            'discount_value' => 50,
            'starts_at' => now()->subDays(10)->toDateString(),
            'ends_at' => now()->subDays(3)->toDateString(),
        ]);

        $patient = Patient::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson($this->tenantUrl('transactions'), [
            'patient_id' => $patient->id,
            'items' => [['service_id' => $service->id, 'qty' => 1]],
        ])->assertCreated()->assertJsonPath('data.subtotal', '100000.00');
    }

    public function test_inactive_promo_does_not_change_price(): void
    {
        $this->actingAsClinicUser();
        $service = $this->service(100000);
        $this->promoFor($service, ['status' => 'inactive', 'discount_value' => 50]);

        $this->getJson($this->tenantUrl('services'))
            ->assertOk()
            ->assertJsonPath('data.0.promo', null);
    }

    public function test_fixed_discount_never_makes_price_negative(): void
    {
        $this->actingAsClinicUser();
        $service = $this->service(50000);
        $this->promoFor($service, ['discount_type' => 'fixed', 'discount_value' => 80000]);

        $this->getJson($this->tenantUrl('services'))
            ->assertOk()
            ->assertJsonPath('data.0.promo.price', '0.00');
    }

    public function test_cashier_cannot_manage_promos(): void
    {
        $this->actingAsClinicUser(ClinicRole::Cashier);
        $service = $this->service();

        $this->getJson($this->tenantUrl('promos'))->assertOk();

        $this->postJson($this->tenantUrl('promos'), [
            'name' => 'Promo Kasir',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDay()->toDateString(),
            'targets' => [['type' => 'service', 'id' => $service->id]],
        ])->assertForbidden();
    }

    public function test_best_promo_wins_when_two_apply(): void
    {
        $this->actingAsClinicUser();
        $service = $this->service(100000);
        $this->promoFor($service, ['discount_type' => 'percent', 'discount_value' => 10]);
        $this->promoFor($service, ['discount_type' => 'fixed', 'discount_value' => 40000]);

        $this->getJson($this->tenantUrl('services'))
            ->assertOk()
            ->assertJsonPath('data.0.promo.price', '60000.00');
    }
}
