<?php

namespace Tests\Feature\Backup;

use App\Actions\Backup\RunBackupAction;
use App\Enums\ClinicRole;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

/**
 * Arsip cadangan basis data untuk pengelola platform.
 *
 * Satu dump memuat data seluruh klinik, jadi penjagaan terpentingnya bukan
 * soal berhasil membuat berkas melainkan soal siapa yang boleh menyentuhnya:
 * admin klinik mana pun yang lolos ke sini akan memegang data klinik lain.
 * Nama berkas datang dari URL, jadi ikut diuji sebagai masukan asing.
 */
class CentralBackupTest extends TestCase
{
    use InteractsWithTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
    }

    private function platformAdmin(): User
    {
        return User::factory()->create([
            'name' => 'Admin Platform',
            'email' => 'platform@sim-clinic.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::PlatformAdmin,
            'status' => UserStatus::Active,
        ]);
    }

    /**
     * pg_dump tidak dijalankan sungguhan di test; yang diuji di sini alur
     * HTTP-nya, bukan perkakas basis datanya.
     */
    private function fakeDump(?string $error = null): void
    {
        $this->instance(RunBackupAction::class, new class($error) extends RunBackupAction
        {
            public function __construct(private readonly ?string $error) {}

            public function execute(?User $causer = null, string $trigger = 'manual'): array
            {
                if ($this->error !== null) {
                    return ['file' => null, 'path' => null, 'size' => 0, 'created_at' => null, 'error' => $this->error];
                }

                $file = 'sim_clinic_laravel_20260821_101500.sql';
                Storage::disk('backups')->put($file, '-- dump');

                return [
                    'file' => $file,
                    'path' => $file,
                    'size' => 7,
                    'created_at' => now()->toIso8601String(),
                    'error' => null,
                ];
            }
        });
    }

    public function test_platform_admin_lists_backups_newest_first(): void
    {
        Sanctum::actingAs($this->platformAdmin());

        $disk = Storage::disk('backups');
        $disk->put('sim_clinic_laravel_20260820_090000.sql', '-- lama');
        $disk->put('sim_clinic_laravel_20260821_090000.sql', '-- baru');
        touch($disk->path('sim_clinic_laravel_20260821_090000.sql'), time() + 60);

        $this->getJson('/api/central/backups')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.file', 'sim_clinic_laravel_20260821_090000.sql');
    }

    /** Daftarnya dibaca dari berkas; yang bukan dump tidak ikut diakui. */
    public function test_files_that_are_not_dumps_are_ignored(): void
    {
        Sanctum::actingAs($this->platformAdmin());

        Storage::disk('backups')->put('catatan.txt', 'bukan cadangan');
        Storage::disk('backups')->put('sim_clinic_laravel_20260821_090000.sql', '-- dump');

        $this->getJson('/api/central/backups')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_platform_admin_creates_a_backup(): void
    {
        Sanctum::actingAs($this->platformAdmin());
        $this->fakeDump();

        $this->postJson('/api/central/backups')
            ->assertCreated()
            ->assertJsonPath('data.file', 'sim_clinic_laravel_20260821_101500.sql');

        Storage::disk('backups')->assertExists('sim_clinic_laravel_20260821_101500.sql');
    }

    /** Kegagalan dump dijawab sebagai galat yang terbaca, bukan 500. */
    public function test_a_failed_dump_is_reported_as_a_form_error(): void
    {
        Sanctum::actingAs($this->platformAdmin());
        $this->fakeDump('Gagal mencadangkan database.');

        $this->postJson('/api/central/backups')
            ->assertStatus(422)
            ->assertJsonPath('errors.backup.0', 'Gagal mencadangkan database.');
    }

    public function test_platform_admin_deletes_a_backup(): void
    {
        Sanctum::actingAs($this->platformAdmin());
        Storage::disk('backups')->put('sim_clinic_laravel_20260821_090000.sql', '-- dump');

        $this->deleteJson('/api/central/backups/sim_clinic_laravel_20260821_090000.sql')
            ->assertOk();

        Storage::disk('backups')->assertMissing('sim_clinic_laravel_20260821_090000.sql');

        $activity = Activity::where('event', 'backup.deleted')->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString('sim_clinic_laravel_20260821_090000.sql', $activity->description);
    }

    /**
     * Nama berkas datang dari URL. Hanya pola yang dihasilkan perintah
     * cadangan yang diterima, dan itu sekaligus menutup jalan "../".
     */
    public function test_a_traversal_filename_is_refused(): void
    {
        Sanctum::actingAs($this->platformAdmin());

        $this->deleteJson('/api/central/backups/'.urlencode('../../.env'))
            ->assertNotFound();

        $this->getJson('/api/central/backups/'.urlencode('../../.env').'/download')
            ->assertNotFound();
    }

    public function test_deleting_a_missing_backup_is_not_found(): void
    {
        Sanctum::actingAs($this->platformAdmin());

        $this->deleteJson('/api/central/backups/sim_clinic_laravel_20990101_000000.sql')
            ->assertNotFound();
    }

    /**
     * Penjagaan terpenting: satu dump memuat data seluruh klinik, jadi admin
     * klinik tidak boleh pernah menyentuhnya.
     */
    public function test_a_clinic_admin_cannot_reach_any_backup_endpoint(): void
    {
        $this->actingAsClinicUser(ClinicRole::Admin);
        Storage::disk('backups')->put('sim_clinic_laravel_20260821_090000.sql', '-- dump');

        $this->getJson('/api/central/backups')->assertForbidden();
        $this->postJson('/api/central/backups')->assertForbidden();
        $this->deleteJson('/api/central/backups/sim_clinic_laravel_20260821_090000.sql')->assertForbidden();

        Storage::disk('backups')->assertExists('sim_clinic_laravel_20260821_090000.sql');
    }

    public function test_backups_require_authentication(): void
    {
        $this->getJson('/api/central/backups')->assertUnauthorized();
        $this->postJson('/api/central/backups')->assertUnauthorized();
    }
}
