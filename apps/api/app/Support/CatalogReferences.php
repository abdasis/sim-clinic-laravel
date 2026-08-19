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
 * Jejak yang induknya sudah dihapus tidak dihitung. Baris nota tidak ikut
 * hilang saat notanya dihapus, begitu pula catatan tindakan saat rekam
 * medisnya dihapus — kalau tetap dihitung, entri yang pernah terjual sekali
 * tidak akan pernah bisa dihapus meski seluruh notanya sudah dibuang.
 *
 * Mutasi stok sengaja tidak dihitung: kalau ikut dihitung, tidak ada satu pun
 * produk yang pernah bisa dihapus — stok awalnya saja sudah meninggalkan satu
 * mutasi. Foreign key-nya restrict, jadi kartu stoknya dibuang sendiri oleh
 * DeleteProductAction saat produknya dihapus.
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
     *
     * Nota yang sudah dihapus tidak dihitung: barisnya tidak muncul di mana
     * pun lagi, jadi menahan katalog atas namanya berarti menyebut nota yang
     * tidak bisa dibereskan admin.
     */
    private static function transactionHit(string $column, int $id): ?string
    {
        $invoice = DB::table('transaction_items')
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transaction_items.'.$column, $id)
            ->whereNull('transactions.deleted_at')
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

    /** Rekam medis yang sudah dihapus tidak lagi menahan, alasan yang sama. */
    private static function treatmentHit(int $serviceId): ?string
    {
        $recordedAt = DB::table('treatment_records')
            ->join('medical_records', 'medical_records.id', '=', 'treatment_records.medical_record_id')
            ->where('treatment_records.service_id', $serviceId)
            ->whereNull('medical_records.deleted_at')
            ->orderBy('treatment_records.created_at')
            ->value('treatment_records.created_at');

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

    /**
     * Buang baris jejak yang induknya sudah dihapus.
     *
     * Memeriksanya saja tidak cukup: foreign key-nya restrict, jadi barisnya
     * harus benar-benar hilang sebelum entri katalognya bisa dihapus. Yang
     * dibuang hanya milik induk yang memang sudah dibuang, jadi tidak ada
     * riwayat hidup yang ikut terpotong.
     *
     * @return int jumlah baris yang dibuang
     */
    public static function clearStaleService(int $serviceId): int
    {
        return self::clearStaleItems('service_id', $serviceId)
            + DB::table('treatment_records')
                ->where('service_id', $serviceId)
                ->whereNotExists(fn ($inner) => $inner->from('medical_records')
                    ->whereColumn('medical_records.id', 'treatment_records.medical_record_id')
                    ->whereNull('medical_records.deleted_at'))
                ->delete();
    }

    public static function clearStaleProduct(int $productId): int
    {
        return self::clearStaleItems('product_id', $productId);
    }

    private static function clearStaleItems(string $column, int $id): int
    {
        return DB::table('transaction_items')
            ->where($column, $id)
            ->whereNotExists(fn ($inner) => $inner->from('transactions')
                ->whereColumn('transactions.id', 'transaction_items.transaction_id')
                ->whereNull('transactions.deleted_at'))
            ->delete();
    }

    private static function asDate(mixed $value): string
    {
        return Carbon::parse((string) $value)->translatedFormat('d F Y');
    }
}
