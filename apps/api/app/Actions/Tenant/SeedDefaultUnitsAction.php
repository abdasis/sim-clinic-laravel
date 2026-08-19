<?php

namespace App\Actions\Tenant;

use App\Actions\LogAuditAction;
use App\Models\Unit;

/**
 * Paket satuan bawaan untuk klinik baru.
 *
 * Klinik yang baru dibuka tidak perlu mengetik satuan satu per satu sebelum
 * bisa menyimpan produk pertamanya. Daftarnya sengaja pendek dan umum di
 * klinik kecantikan — sisanya ditambahkan sendiri lewat menu Satuan.
 */
class SeedDefaultUnitsAction
{
    /** @var array<int, string> */
    public const DEFAULTS = ['pcs', 'botol', 'tube', 'vial', 'paket', 'ampul', 'tube ml'];

    public function handle(int $tenantId): void
    {
        // Idempoten: klinik yang sudah menyusun satuannya sendiri tidak boleh
        // ditimpa oleh seeding ulang atau migrasi yang dijalankan dua kali.
        if (Unit::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $created = [];

        foreach (self::DEFAULTS as $name) {
            $created[] = Unit::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'status' => 'active',
            ])->getAttributes();
        }

        app(LogAuditAction::class)->handle(
            'unit.defaults_seeded',
            null,
            auth()->user(),
            ['tenant_id' => $tenantId, 'new' => ['units' => $created]],
            'Memasang '.count($created).' satuan produk bawaan untuk klinik baru.',
        );
    }
}
