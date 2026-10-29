<?php

return [

    /*
     * Token handshake webhook WhatsApp Cloud API (Meta). Meta mengirimnya
     * kembali sebagai `hub.verify_token` saat mendaftarkan URL webhook; yang
     * tidak sama ditolak. Kosong berarti handshake ditutup seluruhnya.
     */
    'verify_token' => env('META_WHATSAPP_VERIFY_TOKEN'),

    /*
     * App secret aplikasi Meta, dipakai memeriksa header
     * `X-Hub-Signature-256` pada setiap kiriman pesan. Tanpa secret ini
     * kiriman tidak bisa dipastikan berasal dari Meta, jadi webhook menolak
     * semuanya — bukan menerima tanpa pemeriksaan.
     */
    'app_secret' => env('META_WHATSAPP_APP_SECRET'),

];
