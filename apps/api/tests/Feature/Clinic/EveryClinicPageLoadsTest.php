<?php

namespace Tests\Feature\Clinic;

use App\Enums\ClinicRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Sapuan seluruh halaman klinik pada keadaan seperti server.
 *
 * Izin per modul dibuat oleh seeder, sementara deploy hanya menjalankan
 * migrate. Klinik yang perannya disinkronkan sebelum sebuah modul lahir tidak
 * pernah menerima izin modul itu, dan halamannya ditolak 403 — yang di layar
 * terbaca sebagai daftar yang memuat terus. Menemukannya satu per satu lewat
 * laporan pengguna terlalu mahal, jadi seluruh halaman disapu di sini.
 */
class EveryClinicPageLoadsTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    /**
     * Endpoint utama tiap menu di bilah sisi — yang ditembak halamannya saat
     * pertama dibuka.
     *
     * @return array<string, string>
     */
    public static function pages(): array
    {
        return [
            'Dasbor' => 'dashboard/summary',
            'Staf' => 'staff',
            'Peran & Izin' => 'roles',
            'Layanan' => 'services',
            'Pasien' => 'patients',
            'Booking' => 'bookings',
            'Jadwal booking' => 'bookings/schedule?from=2026-08-01&to=2026-08-31',
            'Rekam medis' => 'medical-records',
            'Produk' => 'products',
            'Kategori' => 'categories?filter[type]=service&filter[status]=all',
            'Kasir - riwayat' => 'transactions',
            'Promo' => 'promos',
            'Pengeluaran' => 'expenses',
            'Pengeluaran - ringkasan' => 'expenses/summary',
            'Aturan fee' => 'commission-rules',
            'Broadcast' => 'broadcasts',
            'Broadcast - dasbor' => 'broadcasts/dashboard',
            'Broadcast - pengaturan' => 'broadcasts/settings',
            'Broadcast - koneksi' => 'broadcasts/connection',
            'Template pesan' => 'message-templates',
            'Aturan pengingat' => 'reminder-rules',
            'Profil perusahaan' => 'company-profile/settings',
            'Laporan bulanan' => 'reports/monthly?from=2026-08-01&to=2026-08-31',
            'Laporan omzet' => 'reports/revenue?from=2026-08-01&to=2026-08-31',
            'Laporan layanan' => 'reports/services?from=2026-08-01&to=2026-08-31',
            'Laporan produk' => 'reports/products?from=2026-08-01&to=2026-08-31',
        ];
    }

    /**
     * Kembalikan database ke keadaan klinik lama: perannya ada, tapi seluruh
     * izin modul belum pernah sampai — persis yang terjadi bila seeder tidak
     * ikut jalan di deploy.
     */
    private function forgetAllModulePermissions(): void
    {
        DB::table('role_has_permissions')->delete();
        DB::table('permissions')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function runPermissionBackfill(): void
    {
        (require database_path('migrations/2026_08_19_000000_grant_missing_clinic_permissions.php'))->up();
    }

    public function test_every_clinic_page_answers_after_the_permission_backfill(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->forgetAllModulePermissions();
        $this->runPermissionBackfill();

        $failed = [];

        foreach (self::pages() as $label => $path) {
            $status = $this->getJson($this->tenantUrl($path))->getStatusCode();

            if ($status !== 200) {
                $failed[] = "{$label} ({$path}) -> HTTP {$status}";
            }
        }

        $this->assertSame([], $failed, "Halaman berikut tidak terbuka:\n".implode("\n", $failed));
    }

    public function test_stats_strip_answers_for_every_module_that_has_one(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);

        $this->forgetAllModulePermissions();
        $this->runPermissionBackfill();

        $failed = [];

        foreach (['patients', 'services', 'products', 'bookings', 'medical-records'] as $module) {
            $status = $this->getJson($this->tenantUrl("stats/{$module}"))->getStatusCode();

            if ($status !== 200) {
                $failed[] = "{$module} -> HTTP {$status}";
            }
        }

        $this->assertSame([], $failed, "Ringkasan angka gagal:\n".implode("\n", $failed));
    }
}
