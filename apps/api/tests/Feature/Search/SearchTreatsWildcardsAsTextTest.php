<?php

namespace Tests\Feature\Search;

use App\Models\Patient;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * `%` dan `_` yang diketik pengguna dicari apa adanya.
 *
 * Keduanya berarti "apa saja" bagi LIKE, jadi tanpa dilarikan lebih dulu
 * mencari "Diskon 50%" ikut menarik "Diskon 500 Ribu" — hasil yang salah,
 * dan tidak ada di layar yang menjelaskan kenapa. Yang mengetiknya menyangka
 * kliniknya punya dua promo serupa.
 */
class SearchTreatsWildcardsAsTextTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
    }

    private function service(string $name): Service
    {
        return Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'price' => 100000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function search(string $path, string $keyword): array
    {
        return collect(
            $this->getJson($this->tenantUrl($path).'?'.http_build_query(['search' => $keyword]))
                ->assertOk()
                ->json('data'),
        )->pluck('name')->all();
    }

    public function test_a_percent_sign_matches_only_a_real_percent_sign(): void
    {
        $this->service('Diskon 50% Spesial');
        $this->service('Diskon 500 Ribu');

        $this->assertSame(['Diskon 50% Spesial'], $this->search('services', '50%'));
    }

    public function test_an_underscore_matches_only_a_real_underscore(): void
    {
        $this->service('facial_glow');
        $this->service('facialXglow');

        $this->assertSame(['facial_glow'], $this->search('services', 'facial_glow'));
    }

    /** Kata kunci yang seluruhnya wildcard tidak lagi menarik semua baris. */
    public function test_a_lone_percent_sign_no_longer_matches_everything(): void
    {
        $this->service('Facial Glow');
        $this->service('Peeling');

        $this->assertSame([], $this->search('services', '%'));
    }

    /** Berlaku di semua modul, bukan cuma katalog. */
    public function test_the_same_holds_for_patient_search(): void
    {
        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ibu Sri_Wahyuni']);
        Patient::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ibu SriXWahyuni']);

        $this->assertSame(['Ibu Sri_Wahyuni'], collect(
            $this->getJson($this->tenantUrl('patients').'?'.http_build_query(['search' => 'Sri_Wahyuni']))
                ->assertOk()->json('data'),
        )->pluck('name')->all());
    }
}
