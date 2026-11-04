<?php

namespace App\Services;

use App\Models\WhatsappSetting;
use App\Support\WhatsappGatewayClient;
use App\Support\WhatsappGatewayException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menyiapkan akun gateway WhatsApp untuk satu klinik.
 *
 * Klinik pertama kali mendaftar sebagai tenant gateway, lalu mendapat API key
 * yang dipakai semua panggilan sesi dan pesan. Proses ini bisa terputus di
 * tengah jalan — setelah mendaftar tetapi sebelum API key tersimpan — jadi
 * `ensure()` mengulang dari langkah yang belum selesai alih-alih mendaftar
 * ulang. Webhook tidak dipakai lagi, karena itu tidak ada langkah webhook.
 */
class WhatsappGatewayProvisioningService
{
    public function __construct(private readonly WhatsappGatewayClient $client) {}

    /**
     * Lengkapi kredensial gateway klinik. Aman dipanggil berulang: bila
     * tenant key dan API key sudah ada, gateway tidak dihubungi sama sekali.
     */
    public function ensure(WhatsappSetting $setting): WhatsappSetting
    {
        if ($setting->provisioned()) {
            return $setting;
        }

        $step = 'register';

        try {
            $accessToken = $this->accessToken($setting);

            $step = 'api-key';

            $setting->forceFill([
                'gateway_api_key' => $this->client->createApiKey(
                    (string) $setting->gateway_tenant_key,
                    $accessToken,
                    $this->clinicName($setting),
                ),
                // Kata sandi hanya dibutuhkan untuk login saat percobaan ulang.
                'gateway_password' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::error('Provisioning akun gateway WhatsApp klinik gagal.', [
                'exception' => $e,
                'tenant_id' => $setting->tenant_id,
                'step' => $step,
            ]);

            throw $e;
        }

        return $setting;
    }

    /**
     * Access token untuk membuat API key. Klinik yang belum terdaftar
     * didaftarkan lebih dulu; yang sudah terdaftar masuk dengan kata sandi
     * yang tersimpan dari percobaan sebelumnya.
     */
    private function accessToken(WhatsappSetting $setting): string
    {
        if (blank($setting->gateway_tenant_key)) {
            $password = Str::password(24);

            $auth = $this->client->register(
                $this->clinicName($setting),
                $this->clinicName($setting),
                $this->email($setting),
                $password,
            );

            // Disimpan segera supaya percobaan ulang bisa login, bukan
            // mendaftarkan tenant gateway kedua.
            $setting->forceFill([
                'gateway_tenant_key' => $auth['tenant_key'],
                'gateway_password' => $password,
            ])->save();

            return $auth['access_token'];
        }

        $password = $setting->gateway_password;

        if (blank($password)) {
            throw new WhatsappGatewayException(
                'GATEWAY_PASSWORD_MISSING',
                0,
                null,
                [],
                'Kata sandi akun gateway klinik tidak tersimpan; provisioning tidak bisa dilanjutkan.',
            );
        }

        return $this->client->login($this->email($setting), $password)['access_token'];
    }

    /** Alamat pendaftaran tetap per klinik supaya tidak pernah ada dua akun. */
    private function email(WhatsappSetting $setting): string
    {
        return 'tenant-'.$setting->tenant_id.'@'.config('whatsapp_gateway.account_domain');
    }

    private function clinicName(WhatsappSetting $setting): string
    {
        return $setting->tenant?->name ?: 'Klinik '.$setting->tenant_id;
    }
}
