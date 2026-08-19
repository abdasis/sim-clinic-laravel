<?php

return [

    /*
     * Token ruas URL webhook pesan masuk. Route-nya publik — gateway WhatsApp
     * tidak mengenal sesi login maupun slug klinik — jadi token inilah satu-
     * satunya yang membedakan panggilan sah dari panggilan sembarangan.
     * Kosong berarti webhook ditolak seluruhnya, bukan dibuka untuk semua.
     */
    'webhook_token' => env('WAHA_WEBHOOK_TOKEN'),

];
