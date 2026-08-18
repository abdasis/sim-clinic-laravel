<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Jejak yang ditinggalkan satu layanan atau produk di data klinik.
 *
 * Dipakai untuk memutuskan boleh-tidaknya sebuah entri katalog dihapus
 * permanen. Sebagian foreign key memakai restrict dan akan menolak di tingkat
 * database, tapi penolakan itu datang sebagai galat SQL yang tidak bisa
 * dibaca admin. Jejaknya diperiksa lebih dulu supaya pesannya menyebut alasan
 * yang sebenarnya.
 *
 * Mutasi stok sengaja tidak dihitung: foreign key-nya cascade, jadi riwayat
 * mutasi memang ikut hilang bersama produknya. Kalau ikut dihitung, tidak ada
 * satu pun produk yang pernah bisa dihapus — stok awalnya saja sudah
 * meninggalkan satu mutasi.
 */
class CatalogReferences
{
    /** @var array<int, array{table: string, column: string, reason: string}> */
    private const SERVICE_SOURCES = [
        ['table' => 'transaction_items', 'column' => 'service_id', 'reason' => 'catalog.referenced_by_transaction'],
        ['table' => 'bookings', 'column' => 'service_id', 'reason' => 'catalog.referenced_by_booking'],
        ['table' => 'treatment_records', 'column' => 'service_id', 'reason' => 'catalog.referenced_by_medical_record'],
    ];

    /** @var array<int, array{table: string, column: string, reason: string}> */
    private const PRODUCT_SOURCES = [
        ['table' => 'transaction_items', 'column' => 'product_id', 'reason' => 'catalog.referenced_by_transaction'],
    ];

    /** Alasan pertama yang membuat entri ini tidak boleh dihapus, bila ada. */
    public static function blockingService(int $serviceId): ?string
    {
        return self::firstHit(self::SERVICE_SOURCES, $serviceId)
            ?? self::promoHit('service', $serviceId);
    }

    public static function blockingProduct(int $productId): ?string
    {
        return self::firstHit(self::PRODUCT_SOURCES, $productId)
            ?? self::promoHit('product', $productId);
    }

    /**
     * Promo menunjuk sasarannya secara polimorfik, jadi tidak bisa diperiksa
     * dengan kolom bernama service_id/product_id seperti tabel lain.
     */
    private static function promoHit(string $morphAlias, int $id): ?string
    {
        $exists = DB::table('promo_items')
            ->where('promotable_type', $morphAlias)
            ->where('promotable_id', $id)
            ->exists();

        return $exists ? 'catalog.referenced_by_promo' : null;
    }

    /**
     * @param  array<int, array{table: string, column: string, reason: string}>  $sources
     */
    private static function firstHit(array $sources, int $id): ?string
    {
        foreach ($sources as $source) {
            $exists = DB::table($source['table'])
                ->where($source['column'], $id)
                ->exists();

            if ($exists) {
                return $source['reason'];
            }
        }

        return null;
    }
}
