<?php

namespace App\Actions\Inbox;

use App\Enums\ConversationChannel;
use App\Enums\MessageDirection;
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
    public function handle(Conversation $conversation, string $body): Message
    {
        $this->ensureReplyWindowOpen($conversation);

        try {
            $providerMessageId = $this->deliver($conversation, $body);
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
        ));
    }

    /**
     * Balasan bebas Cloud API hanya berlaku 24 jam sejak pesan terakhir
     * kontak. Lewat dari itu Meta menolak, dan penolakannya baru terlihat
     * setelah pesannya disusun — jadi diperiksa lebih dulu.
     */
    private function ensureReplyWindowOpen(Conversation $conversation): void
    {
        if ($conversation->channel !== ConversationChannel::CloudApi) {
            return;
        }

        $lastInbound = $conversation->messages()
            ->where('direction', MessageDirection::In)
            ->orderByDesc('sent_at')
            ->first();

        if ($lastInbound?->sent_at === null || $lastInbound->sent_at->lt(now()->subDay())) {
            throw ValidationException::withMessages(['body' => __('inbox.window_expired')]);
        }
    }

    /** @return string|null id pesan dari penyedia, bila ia memberikannya */
    private function deliver(Conversation $conversation, string $body): ?string
    {
        if ($conversation->channel === ConversationChannel::CloudApi) {
            $client = app(CloudApiClient::class);

            if (! $client instanceof CloudApiClient) {
                throw ValidationException::withMessages(['body' => __('inbox.cloud_api_not_ready')]);
            }

            return $client->sendText($conversation->contact_phone, $body);
        }

        $client = app(WahaClient::class);

        if (! $client instanceof WahaClient) {
            throw ValidationException::withMessages(['body' => __('inbox.waha_not_ready')]);
        }

        $client->send($conversation->contact_phone, $body);

        return null;
    }
}
