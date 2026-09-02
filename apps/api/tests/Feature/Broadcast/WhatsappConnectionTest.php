<?php

namespace Tests\Feature\Broadcast;

use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Layar Koneksi WhatsApp menanyakan keadaan sesi, bukan mengaduknya.
 *
 * Dialognya menanyakan ulang tiap 4 detik selama belum tersambung, supaya QR
 * yang kedaluwarsa berganti sendiri. Selama tiap tanyaan itu ikut
 * menghidupkan atau memulai ulang sesi, memandangi layarnya sama dengan
 * menggedor gateway 15 kali semenit — sesi yang sedang menyalakan WhatsApp
 * Web tidak pernah sempat selesai, dan nomornya terbaca "keluar terus"
 * oleh yang memakainya.
 */
class WhatsappConnectionTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    private function configured(): void
    {
        WhatsappSetting::create(['tenant_id' => $this->tenant->id, 'session' => 'klinik-uji']);
        WahaSetting::create(['base_url' => 'https://waha.test', 'api_key' => 'kunci']);
    }

    private function gatewayReports(string $status): void
    {
        Http::fake([
            'waha.test/api/*/auth/qr' => Http::response('fake-png-bytes', 200, ['Content-Type' => 'image/png']),
            'waha.test/api/sessions/*' => Http::response(['status' => $status], 200),
            'waha.test/*' => Http::response([], 200),
        ]);
    }

    /** Berapa kali gateway disuruh berbuat sesuatu, bukan cuma ditanya. */
    private function mutations(): int
    {
        return Http::recorded(fn (Request $r) => $r->method() !== 'GET')->count();
    }

    /**
     * Inti laporannya: sesi yang gagal dimulai ulang tiap kali layarnya
     * menanya, jadi tidak pernah sempat bangun.
     */
    public function test_polling_a_failed_session_does_not_restart_it_over_and_over(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        $this->gatewayReports('FAILED');

        foreach (range(1, 5) as $ignored) {
            $this->getJson($this->tenantUrl('broadcasts/connection'))->assertOk();
        }

        $this->assertSame(0, $this->mutations(), 'menanyakan keadaan sesi ikut mengaduk sesinya');
    }

    /** Sesi yang sedang menyala butuh waktu, bukan disuruh mulai lagi. */
    public function test_polling_a_starting_session_leaves_it_alone(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        $this->gatewayReports('STARTING');

        foreach (range(1, 5) as $ignored) {
            $this->getJson($this->tenantUrl('broadcasts/connection'))->assertOk();
        }

        $this->assertSame(0, $this->mutations());
    }

    /** Yang tersambung tetap dilaporkan lengkap dengan nomornya. */
    public function test_a_connected_session_is_reported_with_its_number(): void
    {
        $this->actingAsClinicUser();
        $this->configured();

        Http::fake([
            'waha.test/api/sessions/*' => Http::response([
                'status' => 'WORKING',
                'me' => ['id' => '628123456789@c.us', 'pushName' => 'Meba Clinic'],
            ], 200),
        ]);

        $this->getJson($this->tenantUrl('broadcasts/connection'))
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.number', '628123456789')
            ->assertJsonPath('data.name', 'Meba Clinic');

        $this->assertSame(0, $this->mutations());
    }

    /** QR tetap terbit saat sesinya memang sedang menunggu dipindai. */
    public function test_the_qr_still_comes_through_while_waiting_to_be_scanned(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        $this->gatewayReports('SCAN_QR_CODE');

        $qr = $this->getJson($this->tenantUrl('broadcasts/connection'))
            ->assertOk()
            ->assertJsonPath('data.connected', false)
            ->json('data.qr');

        $this->assertStringStartsWith('data:image/png;base64,', $qr);
    }

    /** Menyalakan sesi jadi tindakan tersendiri, diminta sekali oleh admin. */
    public function test_preparing_starts_a_session_that_is_not_running(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        $this->gatewayReports('STOPPED');

        $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))->assertOk();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_contains($r->url(), '/api/sessions/klinik-uji/start'));
    }

    /** Sesi yang belum ada dibuatkan dulu. */
    public function test_preparing_creates_a_session_that_does_not_exist_yet(): void
    {
        $this->actingAsClinicUser();
        $this->configured();

        Http::fake([
            'waha.test/api/*/auth/qr' => Http::response('fake-png-bytes', 200, ['Content-Type' => 'image/png']),
            'waha.test/api/sessions/klinik-uji' => Http::response([], 404),
            'waha.test/*' => Http::response([], 200),
        ]);

        $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))->assertOk();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === 'https://waha.test/api/sessions'
            && $r['name'] === 'klinik-uji');
    }

    /**
     * Bahkan tindakan yang diminta sendiri tidak boleh bisa digedor.
     *
     * Tombol yang ditekan berkali-kali, atau layar yang dibuka di dua tab,
     * tidak boleh berujung memulai ulang sesi bertubi-tubi. Penjagaannya di
     * sisi server supaya tidak bergantung pada sopan-santun layarnya.
     */
    public function test_preparing_twice_in_a_row_only_acts_once(): void
    {
        $this->actingAsClinicUser();
        $this->configured();
        $this->gatewayReports('STOPPED');

        $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))->assertOk();
        $before = $this->mutations();

        foreach (range(1, 4) as $ignored) {
            $this->postJson($this->tenantUrl('broadcasts/connection/prepare'))->assertOk();
        }

        $this->assertSame($before, $this->mutations(), 'gateway ikut digedor tiap kali tombolnya ditekan');
    }
}
