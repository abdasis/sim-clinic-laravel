<?php

namespace Tests\Feature\Clinic;

use App\Models\Patient;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Kolom urutan yang tidak dikenal ditolak, bukan diteruskan ke basis data.
 *
 * `sort` datang mentah dari query string. Eloquent membungkus nama kolom
 * sehingga tidak ada celah injeksi, tapi kolom yang tidak ada tetap dilempar
 * basis data sebagai galat — dan sampai ke pengguna sebagai layar 500.
 * Sekarang permintaannya tetap dijawab, hanya dengan urutan bawaannya.
 */
class SortWhitelistTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClinicUser();
    }

    private function makePatients(): void
    {
        foreach (['Ani', 'Budi', 'Citra'] as $name) {
            Patient::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name]);
        }
    }

    /** Kolom karangan tidak lagi meruntuhkan halamannya. */
    public function test_an_unknown_sort_column_does_not_break_the_page(): void
    {
        $this->makePatients();

        $this->getJson($this->tenantUrl('patients').'?sort=kolom_karangan')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /** Termasuk yang berbentuk pecahan SQL, bukan sekadar salah ketik. */
    public function test_a_sql_shaped_sort_column_is_ignored(): void
    {
        $this->makePatients();

        $this->getJson($this->tenantUrl('patients').'?sort='.urlencode('name); drop table patients--'))
            ->assertOk();

        $this->assertSame(3, Patient::query()->count());
    }

    /** Kolom yang memang boleh diurut tetap bekerja seperti biasa. */
    public function test_an_allowed_column_still_sorts(): void
    {
        $this->makePatients();

        $names = collect(
            $this->getJson($this->tenantUrl('patients').'?sort=name&direction=desc')
                ->assertOk()->json('data'),
        )->pluck('name')->all();

        $this->assertSame(['Citra', 'Budi', 'Ani'], $names);
    }

    /**
     * Kolom hasil hitungan tidak ada di tabelnya, jadi tidak bisa diurut —
     * tapi jawabannya tetap datang dengan urutan bawaan.
     */
    public function test_a_computed_column_falls_back_instead_of_failing(): void
    {
        $this->makePatients();

        $this->getJson($this->tenantUrl('patients').'?sort=referrer_name')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /** Katalog ikut terjaga, dan urutan abjadnya tetap jadi jaring pengaman. */
    public function test_the_catalog_falls_back_to_its_alphabetical_order(): void
    {
        foreach (['Zebra', 'Apel'] as $name) {
            Service::create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
                'price' => 100000,
                'duration_minutes' => 60,
                'status' => 'active',
            ]);
        }

        $names = collect(
            $this->getJson($this->tenantUrl('services').'?sort=kolom_karangan')
                ->assertOk()->json('data'),
        )->pluck('name')->all();

        $this->assertSame(['Apel', 'Zebra'], $names);
    }
}
