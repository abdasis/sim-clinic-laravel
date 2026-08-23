<?php

namespace Tests\Feature\Clinic;

use App\Actions\Chatbot\SearchServicesAction;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Pencarian tidak memandang besar-kecil huruf, di basis data mana pun.
 *
 * `LIKE` berperilaku berbeda antar driver: SQLite menyamakan huruf besar
 * dan kecil untuk ASCII, PostgreSQL tidak. Kode yang sama karena itu lolos
 * di suite SQLite dan gagal di produksi — mengetik "facial" tidak menemukan
 * "Facial Glow", dan pemakainya menyimpulkan datanya memang tidak ada.
 *
 * Karena itu berkas ini penting dijalankan lewat phpunit.pgsql.xml juga:
 * di SQLite sebagian besarnya lulus bahkan sebelum diperbaiki.
 */
class CaseInsensitiveSearchTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
    }

    private function makeService(string $name): Service
    {
        return Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'price' => 300000,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function names(string $path, string $search): array
    {
        return collect(
            $this->getJson($this->tenantUrl($path).'?search='.urlencode($search))
                ->assertOk()->json('data'),
        )->pluck('name')->all();
    }

    /** Inti keluhannya: mengetik huruf kecil menemukan nama berhuruf kapital. */
    public function test_a_lowercase_query_finds_a_capitalised_service(): void
    {
        $this->makeService('Facial Glow');

        $this->assertSame(['Facial Glow'], $this->names('services', 'facial'));
    }

    /** Dan sebaliknya: huruf kapital menemukan nama berhuruf kecil. */
    public function test_an_uppercase_query_finds_a_lowercase_service(): void
    {
        $this->makeService('peeling ringan');

        $this->assertSame(['peeling ringan'], $this->names('services', 'PEELING'));
    }

    public function test_products_are_searched_the_same_way(): void
    {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Serum Vitamin C',
            'status' => 'active',
        ]);

        $this->assertSame(['Serum Vitamin C'], $this->names('products', 'serum vitamin'));
    }

    /** Potongan di tengah nama tetap ketemu, bukan hanya awalannya. */
    public function test_a_fragment_in_the_middle_still_matches(): void
    {
        $this->makeService('Totok Wajah Premium');

        $this->assertSame(['Totok Wajah Premium'], $this->names('services', 'wajah'));
    }

    /** Kata kunci hasil salin tempel kerap membawa spasi di ujungnya. */
    public function test_surrounding_spaces_do_not_break_the_search(): void
    {
        $this->makeService('Facial Glow');

        $this->assertSame(['Facial Glow'], $this->names('services', '  facial  '));
    }

    /** Yang memang tidak cocok tetap tidak muncul. */
    public function test_an_unrelated_query_finds_nothing(): void
    {
        $this->makeService('Facial Glow');

        $this->assertSame([], $this->names('services', 'peeling'));
    }

    /** Pencarian tidak boleh melonggarkan penyaring lain yang sudah menempel. */
    public function test_the_search_does_not_leak_past_the_status_filter(): void
    {
        $this->makeService('Facial Glow');
        $this->makeService('Facial Arsip')->update(['status' => 'archived']);

        // Bawaannya hanya layanan aktif; yang diarsipkan tidak ikut walau cocok.
        $this->assertSame(['Facial Glow'], $this->names('services', 'facial'));
    }

    /** Pasien dicari lewat nama maupun nomornya, sama-sama tanpa memandang huruf. */
    public function test_patients_are_searched_case_insensitively(): void
    {
        Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ani Lestari',
            'whatsapp' => '081234567890',
        ]);

        $this->assertSame(['Ani Lestari'], $this->names('patients', 'ani lestari'));
        $this->assertSame(['Ani Lestari'], $this->names('patients', 'ANI'));
    }

    /** Staf dicari lewat nama maupun email. */
    public function test_staff_are_searched_case_insensitively(): void
    {
        $names = $this->names('staff', 'STAF');

        $this->assertNotSame([], $names);
    }

    /**
     * Yang paling sering mengetik huruf kecil justru pasien di WhatsApp,
     * jadi jalur chatbot ikut dijaga.
     */
    public function test_the_chatbot_finds_a_service_typed_in_lowercase(): void
    {
        $this->makeService('Facial Glow');

        $found = app(SearchServicesAction::class)->handle('facial');

        $this->assertNotSame([], $found);
        $this->assertSame('Facial Glow', $found[0]['name']);
    }
}
