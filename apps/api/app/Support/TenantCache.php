<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache yang selalu terikat klinik yang sedang aktif.
 *
 * Dibuat sebagai satu pintu, bukan dipanggil langsung lewat facade Cache di
 * tiap tempat, karena satu kunci yang lupa menyebut klinik berarti angka
 * laporan satu klinik tampil di layar klinik lain — kebocoran yang tidak
 * memunculkan galat apa pun dan hanya ketahuan kalau ada yang curiga pada
 * angkanya.
 *
 * Tanpa klinik terikat (CLI, seeding, route pusat), kuncinya memakai penanda
 * 'central' — bukan diam-diam memakai kunci tanpa awalan yang bisa bertabrakan
 * dengan klinik mana pun.
 */
class TenantCache
{
    /** Data yang berubah tiap transaksi: cukup untuk meredam klik beruntun. */
    public const TTL_LIVE = 60;

    /** Daftar penyaring dan sejenisnya: jarang berubah, mahal dihitung. */
    public const TTL_SLOW = 3600;

    /** Periode yang sudah lewat dan tidak akan berubah lagi. */
    public const TTL_SETTLED = 86400;

    public static function remember(string $key, int $seconds, Closure $callback): mixed
    {
        return Cache::remember(self::key($key), $seconds, $callback);
    }

    public static function forget(string $key): void
    {
        Cache::forget(self::key($key));
    }

    public static function key(string $key): string
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        return 'tenant:'.($tenant?->id ?? 'central').':'.$key;
    }
}
