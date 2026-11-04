<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Bagian pengaturan WhatsApp yang memang milik tiap klinik: nama sesi WAHA
 * dan kredensial gateway internal.
 *
 * Alamat server dan kredensial platform-nya central (WahaSetting,
 * WhatsappGatewaySetting). Kolom Meta dibiarkan sampai jalur Cloud API
 * benar-benar dilepas.
 */
#[ScopedBy([TenantScope::class])]
class WhatsappSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'session',
        'phone_number_id',
        'waba_id',
        'reminder_template',
        'daily_message_limit',
        'gateway_tenant_key',
        'gateway_api_key',
        'gateway_webhook_token',
        'gateway_webhook_secret',
        'gateway_session_id',
        'gateway_password',
    ];

    /**
     * Kredensial tidak pernah dikirim balik ke aplikasi web. Token webhook
     * tidak termasuk: ia bagian dari URL yang harus bisa ditampilkan dan
     * dicari saat pesan masuk tiba.
     */
    protected $hidden = [
        'gateway_tenant_key',
        'gateway_api_key',
        'gateway_webhook_secret',
        'gateway_session_id',
        'gateway_password',
    ];

    protected function casts(): array
    {
        return [
            'reminder_template' => 'array',
            // Kredensial gateway membuka nomor WhatsApp klinik; hanya aplikasi
            // (pemegang APP_KEY) yang boleh membacanya kembali.
            'gateway_tenant_key' => 'encrypted',
            'gateway_api_key' => 'encrypted',
            'gateway_webhook_secret' => 'encrypted',
            'gateway_session_id' => 'encrypted',
            'gateway_password' => 'encrypted',
        ];
    }

    /**
     * Siap kirim hanya bila nama sesi dan id sesi gateway sama-sama terisi —
     * keduanya wajib, bukan salah satu.
     */
    public function isConfigured(): bool
    {
        return filled($this->session) && filled($this->gateway_session_id);
    }

    /** Klinik sudah punya akun gateway beserta API key dan webhook-nya. */
    public function provisioned(): bool
    {
        return filled($this->gateway_tenant_key)
            && filled($this->gateway_api_key)
            && filled($this->gateway_webhook_secret);
    }
}
