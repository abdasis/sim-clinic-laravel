<?php

namespace App\Contracts;

/**
 * Kontrak pengirim WhatsApp. Business logic hanya tahu "kirim pesan ke
 * nomor" — penyedianya (gateway HTTP, sidecar QR, API resmi kelak) bebas
 * diganti tanpa menyentuh broadcast/reminder.
 */
interface WhatsappClient
{
    /**
     * Kirim satu pesan. Lempar exception bila gagal — pemanggil yang
     * memutuskan retry dan pencatatan statusnya.
     */
    public function send(string $phone, string $message): void;
}
