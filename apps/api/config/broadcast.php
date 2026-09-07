<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batas pesan keluar per hari
    |--------------------------------------------------------------------------
    |
    | Berapa banyak pesan WhatsApp yang boleh berangkat dari satu nomor klinik
    | dalam sehari. Jeda antar pesan menjaga iramanya, tapi yang paling sering
    | membuat nomor diblokir justru volume hariannya — dan itu tidak terlihat
    | dari jeda mana pun.
    |
    | ponytail: satu angka untuk semua klinik, bisa ditimpa per klinik lewat
    | kolom daily_message_limit di whatsapp_settings. Angkanya konservatif
    | karena WhatsApp tidak pernah mengumumkan ambang aslinya; kalau ada
    | klinik yang rutin menyentuh batas tanpa masalah, naikkan miliknya
    | sendiri, jangan default-nya.
    |
    */

    'daily_limit' => (int) env('BROADCAST_DAILY_LIMIT', 300),

];
