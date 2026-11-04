<?php

namespace App\Actions\Inbox;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappSetting;
use App\Support\PhoneNumber;
use App\Support\WhatsappGatewayClient;
use App\Support\WhatsappGatewayException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kirim satu kontak pada percakapan, lalu catat di utasnya.
 *
 * Gateway belum punya endpoint kartu kontak, jadi kontak dikirim sebagai teks
 * "Nama: +62…". Balasannya tetap tampil rapi di utas penerima.
 */
class SendInboxContactAction
{
    public function handle(Conversation $conversation, string $name, string $phone): Message
    {
        $setting = $this->setting($conversation);

        $contactPhone = PhoneNumber::normalize($phone) ?? ltrim($phone, '+');
        $body = $name.': +'.ltrim($contactPhone, '+');

        try {
            $providerMessageId = app(WhatsappGatewayClient::class)->sendText(
                $setting,
                $conversation->contact_phone,
                $body,
                'inbox-'.Str::uuid(),
            );
        } catch (WhatsappGatewayException $e) {
            Log::error('Gagal mengirim kontak kotak masuk WhatsApp lewat gateway.', [
                'exception' => $e,
                'conversation_id' => $conversation->id,
                'tenant_id' => $conversation->tenant_id,
            ]);

            throw ValidationException::withMessages(['phone' => __('inbox.send_failed')]);
        }

        return app(RecordOutboundMessageAction::class)->handle(
            $conversation->tenant_id,
            $conversation->contact_phone,
            $body,
            $conversation->channel,
            $providerMessageId,
        );
    }

    private function setting(Conversation $conversation): WhatsappSetting
    {
        $setting = WhatsappSetting::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->first();

        if ($setting === null) {
            throw ValidationException::withMessages(['phone' => __('inbox.gateway_not_ready')]);
        }

        return $setting;
    }
}
