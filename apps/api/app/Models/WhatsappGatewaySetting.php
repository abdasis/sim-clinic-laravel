<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Alamat server gateway WhatsApp internal untuk seluruh klinik.
 *
 * Sengaja TIDAK bertenant: servernya satu dan dikelola pengelola platform,
 * sementara kredensial tiap klinik tinggal di WhatsappSetting. Tabelnya
 * berisi satu baris.
 */
class WhatsappGatewaySetting extends Model
{
    protected $fillable = ['base_url'];

    public function isConfigured(): bool
    {
        return filled($this->base_url);
    }
}
