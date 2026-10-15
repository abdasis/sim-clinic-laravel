<?php

namespace Tests\Feature\Promo;

use App\Models\Promo;
use App\Models\Service;
use App\Support\PromoPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Promo yang ada tapi sedang di luar periodenya harus disebut di kasir.
 *
 * Keluhannya: "PROMO HEMAT 10.10 Paket Rejuvanation 50% — hasil akhirnya kok
 * tidak sesuai promo". Hitungannya benar; promonya memang sudah lewat
 * tanggalnya. Tapi di layar kasir yang terlihat hanya harga penuh tanpa satu
 * pun keterangan, jadi yang menagih menyimpulkan sistemnya salah hitung.
 *
 * Yang dijaga di sini: promo kedaluwarsa (dan yang belum mulai) ikut
 * disebutkan, tanpa pernah mengubah harga yang ditagih.
 */
class DormantPromoNoticeTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
        $this->service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Paket Rejuvanation',
            'price' => 1_000_000,
            'duration_minutes' => 90,
            'status' => 'active',
        ]);
    }

    private function promo(string $name, string $starts, string $ends, array $overrides = []): Promo
    {
        $promo = Promo::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'discount_type' => 'percent',
            'discount_value' => 50,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => 'active',
            ...$overrides,
        ]);

        $promo->items()->create([
            'tenant_id' => $this->tenant->id,
            'promotable_type' => 'service',
            'promotable_id' => $this->service->id,
        ]);

        return $promo;
    }

    /** Persis seperti katalog kasir memuatnya. */
    private function catalogRow(): ?array
    {
        return collect(
            $this->getJson($this->tenantUrl('services').'?per_page=100')
                ->assertOk()->json('data'),
        )->firstWhere('id', $this->service->id);
    }

    public function test_an_expired_promo_is_named_in_the_cashier_catalogue(): void
    {
        $this->promo(
            'PROMO HEMAT 10.10 Paket Rejuvanation 50%',
            now()->subDays(14)->toDateString(),
            now()->subDays(5)->toDateString(),
        );

        $row = $this->catalogRow();

        // Harganya tetap penuh: menyebut promo bukan berarti memberlakukannya.
        $this->assertNull($row['promo']);
        $this->assertEqualsWithDelta(1_000_000, (float) $row['price'], 0.01);

        $this->assertSame(
            'PROMO HEMAT 10.10 Paket Rejuvanation 50%',
            $row['promo_dormant']['name'],
        );
        $this->assertSame('ended', $row['promo_dormant']['state']);
        $this->assertSame(now()->subDays(5)->toDateString(), $row['promo_dormant']['ends_at']);
    }

    public function test_a_promo_that_has_not_started_is_named_too(): void
    {
        $this->promo(
            'Promo Akhir Tahun',
            now()->addDays(3)->toDateString(),
            now()->addDays(10)->toDateString(),
        );

        $row = $this->catalogRow();

        $this->assertNull($row['promo']);
        $this->assertSame('upcoming', $row['promo_dormant']['state']);
        $this->assertSame(now()->addDays(3)->toDateString(), $row['promo_dormant']['starts_at']);
    }

    /**
     * Saat promonya sedang jalan, yang kedaluwarsa tidak ikut disebut:
     * dua keterangan berlawanan di layar yang sedang dipakai menagih hanya
     * menambah keraguan.
     */
    public function test_a_running_promo_hides_the_dormant_one(): void
    {
        $this->promo('Sudah lewat', now()->subDays(9)->toDateString(), now()->subDays(2)->toDateString());
        $this->promo('Sedang jalan', now()->subDay()->toDateString(), now()->addDay()->toDateString());

        $row = $this->catalogRow();

        $this->assertSame('Sedang jalan', $row['promo']['name']);
        $this->assertNull($row['promo_dormant']);
    }

    /** Batas waktu terdekat yang dipakai, bukan arahnya. */
    public function test_the_nearest_boundary_wins(): void
    {
        $this->promo('Berakhir kemarin', now()->subDays(20)->toDateString(), now()->subDay()->toDateString());
        $this->promo('Mulai bulan depan', now()->addDays(30)->toDateString(), now()->addDays(40)->toDateString());

        $pricing = new PromoPricing;

        $this->assertSame('Berakhir kemarin', $pricing->dormantFor($this->service)?->name);
    }

    /** Promo yang sengaja dinonaktifkan bukan promo yang sedang menunggu. */
    public function test_a_disabled_promo_is_not_announced(): void
    {
        $this->promo(
            'Dinonaktifkan',
            now()->subDay()->toDateString(),
            now()->addDay()->toDateString(),
            ['status' => 'inactive'],
        );

        $row = $this->catalogRow();

        $this->assertNull($row['promo']);
        $this->assertNull($row['promo_dormant']);
    }

    public function test_a_service_without_any_promo_says_nothing(): void
    {
        $row = $this->catalogRow();

        $this->assertNull($row['promo']);
        $this->assertNull($row['promo_dormant']);
    }

    /**
     * Daftar katalog memuat promonya sekaligus; menyebut yang kedaluwarsa
     * tidak boleh melahirkan satu kueri per baris.
     */
    public function test_the_catalogue_stays_on_a_fixed_number_of_queries(): void
    {
        $this->promo('Sudah lewat', now()->subDays(9)->toDateString(), now()->subDays(2)->toDateString());

        for ($i = 0; $i < 5; $i++) {
            Service::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Layanan '.$i,
                'price' => 100000,
                'duration_minutes' => 30,
                'status' => 'active',
            ]);
        }

        $jumlah = 0;
        \DB::listen(function () use (&$jumlah): void {
            $jumlah++;
        });

        $this->getJson($this->tenantUrl('services').'?per_page=100')->assertOk();

        // Angkanya longgar dengan sengaja: yang dijaga bukan nilai pastinya
        // melainkan bahwa ia tidak tumbuh mengikuti jumlah baris.
        $this->assertLessThan(15, $jumlah, "Katalog menembak $jumlah kueri; kemungkinan N+1.");
    }
}
