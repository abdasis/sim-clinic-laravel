<?php

namespace App\Actions\Inbox;

use App\Actions\LogAuditAction;
use App\Concerns\BroadcastsInboxMessage;
use App\Enums\ConversationChannel;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\CloudApiClient;
use App\Support\CloudApiException;
use App\Support\WahaClient;
use App\Support\WahaException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Beri atau cabut reaksi emoji pada satu pesan, lalu perbarui utasnya.
 *
 * Reaksi tidak punya jendela 24 jam seperti balasan bebas: ia menempel pada
 * pesan yang sudah ada, jadi tidak perlu diperiksa.
 */
class SendInboxReactionAction
{
    use BroadcastsInboxMessage;

    public function handle(Conversation $conversation, Message $message, ?string $emoji): Message
    {
        if ((int) $message->conversation_id !== (int) $conversation->id) {
            throw (new ModelNotFoundException)->setModel(Message::class, [$message->id]);
        }

        $reaction = trim((string) $emoji);
        $providerMessageId = (string) $message->provider_message_id;

        if ($providerMessageId === '') {
            throw ValidationException::withMessages(['emoji' => __('inbox.reaction_unavailable')]);
        }

        try {
            $this->deliver($conversation, $providerMessageId, $reaction);
        } catch (CloudApiException|WahaException $e) {
            Log::error('Gagal mengirim reaksi kotak masuk WhatsApp.', [
                'exception' => $e,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'channel' => $conversation->channel?->value,
            ]);

            throw ValidationException::withMessages(['emoji' => __('inbox.send_failed')]);
        }

        $previous = $message->reaction;
        $message->reaction = $reaction === '' ? null : $reaction;
        $message->save();

        app(LogAuditAction::class)->handle(
            'inbox.message_reaction_updated',
            $message->conversation,
            null,
            [
                'tenant_id' => $conversation->tenant_id,
                'old' => ['reaction' => $previous],
                'new' => ['reaction' => $message->reaction],
                'message_id' => $message->id,
            ],
            $message->reaction === null
                ? 'Mencabut reaksi pada sebuah pesan WhatsApp.'
                : sprintf('Memberi reaksi "%s" pada sebuah pesan WhatsApp.', $message->reaction),
        );

        $this->broadcastInboxMessageStatus($message, (int) $conversation->tenant_id);

        return $message;
    }

    /** Kirim reaksi lewat transport yang sama dengan kanal percakapannya. */
    private function deliver(Conversation $conversation, string $providerMessageId, string $reaction): void
    {
        if ($conversation->channel === ConversationChannel::CloudApi) {
            $client = app(CloudApiClient::class);

            if (! $client instanceof CloudApiClient) {
                throw ValidationException::withMessages(['emoji' => __('inbox.cloud_api_not_ready')]);
            }

            $client->sendReaction($conversation->contact_phone, $providerMessageId, $reaction);

            return;
        }

        $client = app(WahaClient::class);

        if (! $client instanceof WahaClient) {
            throw ValidationException::withMessages(['emoji' => __('inbox.waha_not_ready')]);
        }

        $client->sendReaction($conversation->contact_phone, $providerMessageId, $reaction);
    }
}
