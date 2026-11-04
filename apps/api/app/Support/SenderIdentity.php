<?php

namespace App\Support;

/**
 * Identitas pengirim pesan WhatsApp masuk.
 *
 * Sejak WhatsApp memakai LID (identitas samaran) untuk chat pribadi, `remoteJid`
 * bisa berbentuk `239959873220620@lid` dan bukan nomor telepon. Nomor asli
 * kadang ikut pada `remoteJidAlt`, kadang tidak sama sekali — dan pesan tanpa
 * nomor asli itu tetap harus bisa dibalas, bukan dibuang.
 *
 * Aturan yang dipakai:
 * - JID biasa (`...@s.whatsapp.net`, `...@c.us`, atau tanpa domain) -> nomor
 *   ternormalisasi, bentuk yang selama ini dipakai.
 * - LID yang menyertakan nomor asli -> nomor asli tersebut.
 * - LID tanpa nomor asli -> JID utuh (`...@lid`) sebagai identitas. Gateway
 *   meneruskan tujuan yang mengandung `@` apa adanya, jadi balasan tetap
 *   sampai ke pengirim yang sama.
 */
class SenderIdentity
{
    /** Batas kolom `conversations.contact_phone`. */
    private const MAX_LENGTH = 32;

    public static function isLid(?string $jid): bool
    {
        if ($jid === null) {
            return false;
        }

        return str_ends_with(mb_strtolower(trim($jid)), '@lid');
    }

    /**
     * Identitas yang dipakai sebagai kunci percakapan sekaligus tujuan balasan,
     * atau null bila JID memang tidak bisa dipakai.
     */
    public static function resolve(string $remoteJid, mixed $remoteJidAlt = null): ?string
    {
        $jid = trim($remoteJid);

        if ($jid === '') {
            return null;
        }

        if (! self::isLid($jid)) {
            // Domain dibuang, begitu pula nomor perangkat di belakang ':'.
            // Normalisasi menyapu seluruh karakter bukan angka, jadi
            // "628xx:12@s.whatsapp.net" tanpa pemotongan berubah menjadi nomor
            // yang tidak pernah ada.
            $user = strtok(strtok($jid, '@') ?: '', ':') ?: '';

            return PhoneNumber::normalize($user);
        }

        $alternate = is_string($remoteJidAlt) ? PhoneNumber::normalize($remoteJidAlt) : null;

        if ($alternate !== null) {
            return $alternate;
        }

        // Tanpa nomor asli: simpan JID apa adanya supaya balasan bisa dikirim
        // ke chat yang sama. Dipotong agar selalu muat di kolom.
        return mb_substr($jid, 0, self::MAX_LENGTH);
    }

    /** Bagian angka dari identitas LID, untuk ditampilkan. */
    public static function digits(string $identity): string
    {
        return preg_replace('/\D+/', '', strtok($identity, '@') ?: '') ?: $identity;
    }

    /**
     * Kunci percakapan yang dipakai lapisan kotak masuk. Nomor telepon
     * dinormalisasi seperti biasa; identitas LID tanpa nomor asli dipakai apa
     * adanya karena bentuk itu juga yang dipakai saat membalas.
     */
    public static function key(?string $phone): ?string
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized !== null) {
            return $normalized;
        }

        if (self::isLid($phone)) {
            return mb_substr(trim((string) $phone), 0, self::MAX_LENGTH);
        }

        return null;
    }

    /**
     * Bentuk yang layak ditampilkan ke operator: nomor telepon apa adanya,
     * atau angka LID tanpa akhiran `@lid`.
     */
    public static function display(string $identity): string
    {
        return self::isLid($identity) ? self::digits($identity) : $identity;
    }
}
