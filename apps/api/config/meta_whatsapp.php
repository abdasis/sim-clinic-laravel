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

    /*
     * Token akses aplikasi Meta untuk mengirim pesan lewat Cloud API. Hanya
     * dibaca dari konfigurasi; tidak pernah disimpan di basis data maupun
     * dicatat ke log. Kosong berarti pengiriman keluar lewat jalur ini ditolak.
     */
    'access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),

    /* Versi Graph API yang dipakai. Naikkan saat Meta menghentikan versi lama. */
    'api_version' => env('META_WHATSAPP_API_VERSION', 'v21.0'),

    'base_url' => env('META_WHATSAPP_BASE_URL', 'https://graph.facebook.com'),

];
