<?php

namespace App\Actions\Inbox;

use App\Concerns\EnsuresReplyWindow;
use App\Enums\ConversationChannel;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\CloudApiClient;
use App\Support\CloudApiException;
use App\Support\WahaClient;
use App\Support\WahaException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Kirim satu balasan teks pada sebuah percakapan, lalu catat di utasnya.
 *
 * Gerbang transportnya ditentukan kanal percakapan, bukan pilihan bebas:
 * pesan yang masuk lewat Cloud API hanya bisa dibalas lewat Cloud API.
 */
class SendInboxMessageAction
{
    use EnsuresReplyWindow;

    public function handle(Conversation $conversation, string $body, ?int $replyToMessageId = null): Message
    {
        $this->ensureReplyWindowOpen($conversation);

        $replyTo = $replyToMessageId === null
            ? null
            : $conversation->messages()->whereKey($replyToMessageId)->first();

        if ($replyToMessageId !== null && $replyTo === null) {
            throw ValidationException::withMessages(['reply_to_message_id' => __('inbox.reply_not_found')]);
        }

        try {
            $providerMessageId = $this->deliver($conversation, $body, $replyTo?->provider_message_id);
        } catch (CloudApiException|WahaException $e) {
            Log::error('Gagal mengirim balasan kotak masuk WhatsApp.', [
                'exception' => $e,
                'conversation_id' => $conversation->id,
                'channel' => $conversation->channel?->value,
            ]);

            throw ValidationException::withMessages(['body' => __('inbox.send_failed')]);
        }

        return DB::transaction(fn (): Message => app(RecordOutboundMessageAction::class)->handle(
            $conversation->tenant_id,
            $conversation->contact_phone,
            $body,
            $conversation->channel,
            $providerMessageId,
            [],
            $replyTo,
        ));
    }

    /** @return string|null id pesan dari penyedia, bila ia memberikannya */
    private function deliver(Conversation $conversation, string $body, ?string $replyToMessageId): ?string
    {
        if ($conversation->channel === ConversationChannel::CloudApi) {
            $client = app(CloudApiClient::class);

            if (! $client instanceof CloudApiClient) {
                throw ValidationException::withMessages(['body' => __('inbox.cloud_api_not_ready')]);
            }

            return $client->sendText($conversation->contact_phone, $body, $replyToMessageId);
        }

        $client = app(WahaClient::class);

        if (! $client instanceof WahaClient) {
            throw ValidationException::withMessages(['body' => __('inbox.waha_not_ready')]);
        }

        $client->send($conversation->contact_phone, $body, $replyToMessageId);

        return null;
    }
}
