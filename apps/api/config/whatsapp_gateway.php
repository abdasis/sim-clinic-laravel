<?php

return [

    /*
     * Alamat server gateway WhatsApp internal. Nilai di
     * `whatsapp_gateway_settings.base_url` menang atas ini bila terisi;
     * config hanya jaring pertama untuk pemasangan baru.
     */
    'base_url' => env('WHATSAPP_GATEWAY_BASE_URL', 'http://localhost:5173'),

    /*
     * Domain email akun tenant gateway saat provisioning otomatis. Alamat
     * emailnya dibentuk dari id klinik supaya satu klinik tidak pernah
     * mendaftar dua tenant gateway.
     */
    'account_domain' => env('WHATSAPP_GATEWAY_ACCOUNT_DOMAIN', 'gateway.local'),

];
