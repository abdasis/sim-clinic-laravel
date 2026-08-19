<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Jejak yang ditinggalkan satu layanan atau produk di data klinik.
 *
 * Dipakai untuk memutuskan boleh-tidaknya sebuah entri katalog dihapus
 * permanen. Sebagian foreign key memakai restrict dan akan menolak di tingkat
 * database, tapi penolakan itu datang sebagai galat SQL yang tidak bisa
 * dibaca admin. Jejaknya diperiksa lebih dulu supaya pesannya menyebut alasan
 * yang sebenarnya — lengkap dengan penunjuk konkret ke data yang menahannya,
 * karena "sudah pernah terjual" saja masih menyisakan pertanyaan "terjual di
 * nota yang mana?".
 *
 * Mutasi stok sengaja tidak dihitung: foreign key-nya cascade, jadi riwayat
 * mutasi memang ikut hilang bersama produknya. Kalau ikut dihitung, tidak ada
 * satu pun produk yang pernah bisa dihapus — stok awalnya saja sudah
 * meninggalkan satu mutasi.
 */
class CatalogReferences
{
    /** Alasan pertama yang membuat entri ini tidak boleh dihapus, bila ada. */
    public static function blockingService(int $serviceId): ?string
    {
        return self::transactionHit('service_id', $serviceId)
            ?? self::bookingHit($serviceId)
            ?? self::treatmentHit($serviceId)
            ?? self::promoHit('service', $serviceId);
    }

    public static function blockingProduct(int $productId): ?string
    {
        return self::transactionHit('product_id', $productId)
            ?? self::promoHit('product', $productId);
    }

    /**
     * Nota yang memuat entri ini. Nomor notanya disebut supaya admin tahu
     * persis nota mana yang harus dibereskan lebih dulu bila memang data
     * percobaan.
     */
    private static function transactionHit(string $column, int $id): ?string
    {
        $invoice = DB::table('transaction_items')
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transaction_items.'.$column, $id)
            ->orderBy('transactions.issued_at')
            ->value('transactions.invoice_number');

        return $invoice === null
            ? null
            : __('catalog.referenced_by_transaction', ['ref' => $invoice]);
    }

    private static function bookingHit(int $serviceId): ?string
    {
        $startAt = DB::table('bookings')
            ->where('service_id', $serviceId)
            ->orderBy('start_at')
            ->value('start_at');

        return $startAt === null
            ? null
            : __('catalog.referenced_by_booking', ['ref' => self::asDate($startAt)]);
    }

    private static function treatmentHit(int $serviceId): ?string
    {
        $recordedAt = DB::table('treatment_records')
            ->where('service_id', $serviceId)
            ->orderBy('created_at')
            ->value('created_at');

        return $recordedAt === null
            ? null
            : __('catalog.referenced_by_medical_record', ['ref' => self::asDate($recordedAt)]);
    }

    /**
     * Promo menunjuk sasarannya secara polimorfik, jadi tidak bisa diperiksa
     * dengan kolom bernama service_id/product_id seperti tabel lain. Nilai
     * yang tersimpan adalah alias morph, bukan nama kelas — lihat MORPH_MAP
     * di AppServiceProvider.
     */
    private static function promoHit(string $morphAlias, int $id): ?string
    {
        $name = DB::table('promo_items')
            ->join('promos', 'promos.id', '=', 'promo_items.promo_id')
            ->where('promo_items.promotable_type', $morphAlias)
            ->where('promo_items.promotable_id', $id)
            ->value('promos.name');

        return $name === null
            ? null
            : __('catalog.referenced_by_promo', ['ref' => $name]);
    }

    private static function asDate(mixed $value): string
    {
        return Carbon::parse((string) $value)->translatedFormat('d F Y');
    }
}
