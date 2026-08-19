<?php

namespace App\Actions\Tenant;

use App\Actions\LogAuditAction;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Buat/selaraskan role klinik milik satu tenant beserta permission-nya.
 * Dipakai saat tenant baru dibuat dan saat seeding.
 */
class SyncTenantClinicRolesAction
{
    public const GUARD = 'sanctum';

    /**
     * Template bawaan peran klinik → modul (spec 002, R2 matriks).
     * 'r' = hanya baca ({module}.view), 'rw' = baca + kelola ({module}.manage).
     *
     * Sejak izin bisa diatur per klinik lewat UI, ini bukan lagi kebenaran
     * saat berjalan — yang berlaku adalah isi `role_has_permissions`. Peta ini
     * dipakai untuk menyemai klinik baru dan untuk tombol "kembalikan ke
     * bawaan".
     *
     * Menambah modul cukup di sini; permission dan role ikut tersinkron
     * lewat RolesAndPermissionsSeeder.
     *
     * @var array<string, array<string, string>> role => [module => 'r'|'rw']
     */
    public const MATRIX = [
        'admin' => [
            'role' => 'rw', 'staff' => 'rw', 'service' => 'rw', 'patient' => 'rw', 'booking' => 'rw',
            'medical_record' => 'rw', 'product' => 'rw', 'inventory' => 'rw',
            'transaction' => 'rw', 'invoice' => 'rw', 'report' => 'rw',
            'content' => 'rw', 'promo' => 'rw', 'expense' => 'rw', 'broadcast' => 'rw',
            'category' => 'rw',
            // Satuan hanya dipakai formulir produk, dan produk hanya admin.
            'unit' => 'rw',
            // Log hanya dibaca — barisnya ditulis sistem, tidak pernah dari layar.
            'activity_log' => 'r',
        ],
        'doctor' => [
            'patient' => 'rw', 'booking' => 'rw', 'medical_record' => 'rw', 'service' => 'r',
            // Perlu membaca kategori layanan saat menelusuri katalog.
            'category' => 'r',
        ],
        'therapist' => [
            'patient' => 'r', 'booking' => 'rw', 'medical_record' => 'rw', 'service' => 'r',
            'category' => 'r',
        ],
        'cashier' => [
            'patient' => 'rw', 'transaction' => 'rw', 'invoice' => 'rw',
            // Kasir hanya perlu menelusuri asal potongan harga, bukan mengubahnya.
            'promo' => 'r',
        ],
    ];

    /**
     * Kembalikan seluruh peran klinik ke template bawaan.
     *
     * Menimpa apa pun yang sudah diatur admin, jadi hanya untuk klinik baru
     * dan untuk aksi "kembalikan ke bawaan" yang memang diminta.
     */
    public function handle(int $tenantId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($tenantId);

        foreach (self::MATRIX as $roleName => $modules) {
            Role::findOrCreate($roleName, self::GUARD)
                ->syncPermissions(self::permissionNamesFor($modules));
        }

        $registrar->setPermissionsTeamId($previous);
        $registrar->forgetCachedPermissions();

        app(LogAuditAction::class)->handle(
            'role.roles_synced',
            null,
            auth()->user(),
            [
                'tenant_id' => $tenantId,
                'new' => ['roles' => array_keys(self::MATRIX)],
            ],
            'Menyiapkan peran klinik bawaan ('.implode(', ', array_keys(self::MATRIX)).') beserta izinnya.',
        );
    }

    /**
     * Pastikan baris peran klinik ada, tanpa menyentuh izinnya.
     *
     * Dipakai di jalur yang cuma butuh peran itu ada — menerima undangan,
     * misalnya. Memakai handle() di sana akan mengembalikan seluruh izin
     * klinik ke bawaan hanya karena ada staf baru bergabung.
     */
    public function ensureRolesExist(int $tenantId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($tenantId);

        foreach (array_keys(self::MATRIX) as $roleName) {
            Role::findOrCreate($roleName, self::GUARD);
        }

        $registrar->setPermissionsTeamId($previous);
        $registrar->forgetCachedPermissions();

        app(LogAuditAction::class)->handle(
            'role.roles_synced',
            null,
            auth()->user(),
            [
                'tenant_id' => $tenantId,
                'new' => ['roles' => array_keys(self::MATRIX)],
            ],
            'Menyiapkan peran klinik bawaan ('.implode(', ', array_keys(self::MATRIX)).') beserta izinnya.',
        );
    }

    /**
     * Seluruh modul yang dikenal, gabungan dari semua peran.
     *
     * @return array<int, string>
     */
    public static function modules(): array
    {
        $modules = [];

        foreach (self::MATRIX as $byModule) {
            $modules = array_merge($modules, array_keys($byModule));
        }

        return array_values(array_unique($modules));
    }

    /**
     * @param  array<string, string>  $modules  module => 'r'|'rw'
     * @return array<int, string>
     */
    public static function permissionNamesFor(array $modules): array
    {
        $names = [];

        foreach ($modules as $module => $access) {
            $names[] = "{$module}.view";

            if ($access === 'rw') {
                $names[] = "{$module}.manage";
            }
        }

        return $names;
    }
}
