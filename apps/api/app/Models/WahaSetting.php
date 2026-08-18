<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Alamat dan kredensial server WAHA untuk seluruh klinik.
 *
 * Sengaja TIDAK bertenant: servernya satu dan dikelola pengelola platform,
 * sementara tiap klinik hanya menentukan nama sesinya sendiri di
 * WhatsappSetting. Tabelnya berisi satu baris.
 */
class WahaSetting extends Model
{
    protected $fillable = ['base_url', 'api_key'];

    /** Kredensial tidak pernah dikirim balik; cukup penanda sudah terisi. */
    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            // Kunci ini membuka WhatsApp seluruh klinik, jadi tidak disimpan
            // terbaca di basis data — hanya aplikasi (yang pegang APP_KEY)
            // yang bisa membukanya kembali.
            'api_key' => 'encrypted',
        ];
    }

    public function isConfigured(): bool
    {
        return filled($this->base_url) && filled($this->api_key);
    }
}
