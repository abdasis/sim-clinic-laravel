<?php

namespace App\Support;

/**
 * Jejak yang ditinggalkan sebuah booking di data klinik.
 *
 * Menghapus booking berguna untuk jadwal yang salah ketik atau dobel —
 * membatalkannya hanya menyisakan baris merah di kalender untuk sesuatu yang
 * tidak pernah benar-benar ada. Tapi begitu kunjungannya meninggalkan jejak,
 * menghapusnya bukan lagi merapikan melainkan menghilangkan fakta.
 *
 * Dua jejak yang menahan, dengan alasan berbeda:
 *
 * - `medical_records` ditahan foreign key RESTRICT, jadi penghapusannya
 *   memang akan gagal — tapi sebagai galat basis data mentah yang sampai ke
 *   pengguna sebagai layar 500. Diperiksa lebih dulu supaya penolakannya
 *   bisa dibaca.
 * - `transactions` justru tidak menahan apa pun: foreign key-nya nullOnDelete,
 *   jadi notanya tetap ada tapi diam-diam kehilangan tautan ke kunjungannya.
 *   Uang yang sudah berpindah berarti kunjungannya sungguh terjadi, dan itu
 *   bukan jadwal salah ketik yang perlu dibuang.
 *
 * `booking_reminders` sengaja tidak dihitung: pengingat memang ikut hilang
 * bersama jadwalnya, dan itu yang diinginkan.
 */
class BookingReferences extends ModelReferences
{
    protected function sources(): array
    {
        return [
            ['table' => 'medical_records', 'column' => 'booking_id'],
            ['table' => 'transactions', 'column' => 'booking_id'],
        ];
    }
}
