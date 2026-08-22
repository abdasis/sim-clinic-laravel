<?php

namespace Tests\Feature\Chatbot;

use App\Actions\Chatbot\GetProductStockAction;
use App\Actions\Chatbot\SearchServicesAction;
use App\Enums\PromoStatus;
use App\Models\Product;
use App\Models\Promo;
use App\Models\Service;
use App\Support\PromoPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Harga yang disebut chatbot harus sama dengan yang ditagih kasir.
 *
 * Sebelumnya chatbot menjawab harga katalog apa adanya, jadi pasien
 * dijanjikan angka yang tidak pernah cocok dengan notanya begitu promo
 * sedang berjalan — dan tidak pernah tahu ia sedang berhak atas potongan.
 * Kini keduanya membaca PromoPricing, sumber yang sama.
 */
class ChatbotPromoAwarePriceTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function makeService(string $name = 'Facial Glow', float $price = 500000): Service
    {
        return Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'price' => $price,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
    }

    private function makeProduct(string $name = 'Serum Vitamin C', float $price = 200000): Product
    {
        return Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'price' => $price,
        ]);
    }

    private function promoFor(
        Service|Product $target,
        string $type = 'percent',
        float $value = 20,
        PromoStatus $status = PromoStatus::Active,
        ?string $starts = null,
        ?string $ends = null,
    ): Promo {
        $promo = Promo::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Promo Kemerdekaan',
            'discount_type' => $type,
            'discount_value' => $value,
            'starts_at' => $starts ?? now()->subDay(),
            'ends_at' => $ends ?? now()->addWeek(),
            'status' => $status,
        ]);

        $promo->items()->create([
            'tenant_id' => $this->tenant->id,
            'promotable_type' => $target->getMorphClass(),
            'promotable_id' => $target->id,
        ]);

        return $promo;
    }

    public function test_a_service_on_promo_is_quoted_at_the_discounted_price(): void
    {
        $this->actingAsClinicUser();
        $service = $this->makeService();
        $this->promoFor($service);

        $row = app(SearchServicesAction::class)->handle()[0];

        $this->assertSame(400000.0, $row['price']);
        $this->assertSame(500000.0, $row['normal_price']);
        $this->assertSame('Promo Kemerdekaan', $row['promo']['name']);
        $this->assertSame(now()->addWeek()->format('Y-m-d'), $row['promo']['ends_at']);
    }

    /** Tanpa promo, bentuk jawabannya tetap seperti dulu. */
    public function test_a_service_without_a_promo_keeps_its_list_price(): void
    {
        $this->actingAsClinicUser();
        $this->makeService();

        $row = app(SearchServicesAction::class)->handle()[0];

        $this->assertSame(500000.0, $row['price']);
        $this->assertArrayNotHasKey('promo', $row);
        $this->assertArrayNotHasKey('normal_price', $row);
    }

    public function test_a_product_on_promo_is_quoted_at_the_discounted_price(): void
    {
        $this->actingAsClinicUser();
        $product = $this->makeProduct();
        $this->promoFor($product, 'fixed', 50000);

        $row = app(GetProductStockAction::class)->handle()[0];

        $this->assertSame(150000.0, $row['price']);
        $this->assertSame(200000.0, $row['normal_price']);
        $this->assertSame('Promo Kemerdekaan', $row['promo']['name']);
        // Stok tetap ikut; promo tidak boleh menggeser informasi lain.
        $this->assertArrayHasKey('stock_balance', $row);
    }

    /** Promo yang belum mulai atau sudah lewat tidak boleh memotong harga. */
    public function test_a_promo_outside_its_window_does_not_change_the_price(): void
    {
        $this->actingAsClinicUser();
        $service = $this->makeService();
        $this->promoFor($service, starts: now()->addWeek()->toDateString(), ends: now()->addMonth()->toDateString());

        $row = app(SearchServicesAction::class)->handle()[0];

        $this->assertSame(500000.0, $row['price']);
        $this->assertArrayNotHasKey('promo', $row);
    }

    /** Promo yang dimatikan di menu promosi ikut berhenti berlaku. */
    public function test_an_inactive_promo_does_not_change_the_price(): void
    {
        $this->actingAsClinicUser();
        $service = $this->makeService();
        $this->promoFor($service, status: PromoStatus::Inactive);

        $row = app(SearchServicesAction::class)->handle()[0];

        $this->assertSame(500000.0, $row['price']);
        $this->assertArrayNotHasKey('promo', $row);
    }

    /**
     * Harga yang dikutip chatbot dan yang dihitung kasir harus berasal dari
     * satu sumber; selisih di antara keduanya jadi perdebatan di meja kasir.
     */
    public function test_the_quoted_price_matches_what_the_cashier_computes(): void
    {
        $this->actingAsClinicUser();
        $service = $this->makeService();
        $this->promoFor($service, 'percent', 35);

        $quoted = app(SearchServicesAction::class)->handle()[0]['price'];
        $cashier = (new PromoPricing)->priceFor($service);

        $this->assertSame($cashier, $quoted);
    }

    /** Bila satu barang kena beberapa promo, yang dipakai yang termurah. */
    public function test_the_cheapest_promo_wins(): void
    {
        $this->actingAsClinicUser();
        $service = $this->makeService();
        $this->promoFor($service, 'percent', 10);
        $this->promoFor($service, 'percent', 40);

        $this->assertSame(300000.0, app(SearchServicesAction::class)->handle()[0]['price']);
    }
}
