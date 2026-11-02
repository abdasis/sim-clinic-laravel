<?php

namespace App\Actions\Inbox;

use App\Concerns\EnsuresReplyWindow;
use App\Enums\ConversationChannel;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\CloudApiClient;
use App\Support\CloudApiException;
use App\Support\PhoneNumber;
use App\Support\WahaClient;
use App\Support\WahaException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Kirim satu kartu kontak pada percakapan, lalu catat di utasnya.
 *
 * Cloud API mengirimnya sebagai pesan bertipe `contacts`. WAHA belum punya
 * endpoint kartu kontak yang bisa dipastikan ada di semua versi, jadi di sana
 * kontak dikirim sebagai teks biasa — lihat catatan di deliver().
 */
class SendInboxContactAction
{
    use EnsuresReplyWindow;

    public function handle(Conversation $conversation, string $name, string $phone): Message
    {
        $this->ensureReplyWindowOpen($conversation, 'phone');

        $contactPhone = PhoneNumber::normalize($phone) ?? ltrim($phone, '+');
        $body = $name.': +'.ltrim($contactPhone, '+');

        try {
            $providerMessageId = $this->deliver($conversation, $name, $contactPhone, $body);
        } catch (CloudApiException|WahaException $e) {
            Log::error('Gagal mengirim kontak kotak masuk WhatsApp.', [
                'exception' => $e,
                'conversation_id' => $conversation->id,
                'channel' => $conversation->channel?->value,
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

    /** @return string|null id pesan dari penyedia, bila ia memberikannya */
    private function deliver(Conversation $conversation, string $name, string $contactPhone, string $body): ?string
    {
        if ($conversation->channel === ConversationChannel::CloudApi) {
            $client = app(CloudApiClient::class);

            if (! $client instanceof CloudApiClient) {
                throw ValidationException::withMessages(['phone' => __('inbox.cloud_api_not_ready')]);
            }

            return $client->sendContacts($conversation->contact_phone, $name, $contactPhone);
        }

        $client = app(WahaClient::class);

        if (! $client instanceof WahaClient) {
            throw ValidationException::withMessages(['phone' => __('inbox.waha_not_ready')]);
        }

        // shortcut: WAHA tidak punya /api/sendContact yang dijamin ada di semua
        // versi, jadi kontak dikirim sebagai teks "Nama: +62…". Upgrade bila
        // gateway sudah dipastikan mendukung endpoint kartu kontak.
        $client->send($conversation->contact_phone, $body);

        return null;
    }
}
