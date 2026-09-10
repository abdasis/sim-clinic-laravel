<?php

namespace App\Support;

/**
 * Alamat klinik sebagaimana layak dicetak di nota thermal.
 *
 * Tautan peta dibuang apa pun bentuknya. Di layar ia berguna, di atas kertas
 * 48mm ia deretan karakter acak yang tidak bisa diklik siapa pun — dua baris
 * penuh terbuang untuk sesuatu yang tidak pernah dibaca. Dibuang di sini,
 * bukan dengan meminta orang merapikan isi kolomnya, karena kolom alamat akan
 * diisi ulang oleh orang lain lagi nanti dan hasilnya harus tetap benar.
 *
 * ponytail: aturan yang sama juga hidup di `receipt.tsx` untuk nota yang
 * dirender di layar. Dua salinan karena memang ada dua perender nota — HTML
 * untuk cetak langsung dan Blade untuk unduhan PDF — dan keduanya sudah
 * menduplikasi seluruh tata letaknya sejak awal. Menyatukannya berarti satu
 * perender saja, dan itu pekerjaan tersendiri; sampai saat itu, perubahan di
 * sini wajib diikutkan ke sana.
 */
class ReceiptAddress
{
    /**
     * Pagar terakhir panjang alamat di kertas 48mm — kira-kira tiga baris.
     *
     * Sengaja longgar. Memotong lebih pendek menghasilkan penggalan yang tidak
     * menuntun siapa pun ke mana pun ("Blok A 10, Tanjung..."), dan alamat yang
     * salah lebih buruk daripada alamat yang panjang. Alamat yang benar-benar
     * ringkas hanya bisa datang dari orang yang menulisnya di profil klinik.
     */
    private const LIMIT = 90;

    public static function format(?string $raw): ?string
    {
        if (blank($raw)) {
            return null;
        }

        $cleaned = preg_replace(
            [
                // Tautan berskema, termasuk yang tercetak terpenggal ("os://").
                '~\b[a-z][a-z0-9+.-]*://\S+~i',
                // Tautan telanjang tanpa skema.
                '~\b(?:www\.|[a-z0-9-]+\.(?:com|id|co|net|org|goo\.gl))/?\S*~i',
                '~\s+~',
            ],
            [' ', ' ', ' '],
            $raw,
        );

        // Tanda baca yang menggantung setelah tautannya dibuang.
        $cleaned = trim(preg_replace('~\s*[,.;|-]\s*$~', '', trim($cleaned)));

        if ($cleaned === '') {
            return null;
        }

        if (mb_strlen($cleaned) <= self::LIMIT) {
            return $cleaned;
        }

        // Dipotong di batas kata supaya tidak ada penggalan kata menggantung.
        $head = mb_substr($cleaned, 0, self::LIMIT);
        $cut = max(mb_strrpos($head, ' ') ?: 0, mb_strrpos($head, ',') ?: 0);

        return rtrim($cut > 0 ? mb_substr($head, 0, $cut) : $head, ',.; ').'…';
    }
}
