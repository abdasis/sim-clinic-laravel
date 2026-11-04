<?php

namespace App\Actions\Inbox;

use App\Actions\LogAuditAction;
use App\Concerns\BroadcastsInboxMessage;
use App\Enums\ConversationChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\SenderIdentity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Catat pesan yang sudah berhasil dikirim — balasan manual, balasan chatbot,
 * maupun blast broadcast — ke utas kontak yang bersangkutan.
 *
 * Percakapan baru dibuat bila kontaknya belum pernah menulis: blast ke nomor
 * baru tetap harus terlihat di kotak masuk, kalau tidak riwayatnya hilang
 * begitu saja. Penghitung belum dibaca sengaja tidak disentuh.
 */
class RecordOutboundMessageAction
{
    use BroadcastsInboxMessage;

    /**
     * @param  array<string, mixed>  $media  kolom lampiran (media_type, media_path, …)
     */
    public function handle(
        int $tenantId,
        string $phone,
        string $body,
        ConversationChannel $channel,
        ?string $providerMessageId = null,
        array $media = [],
        ?Message $replyTo = null,
    ): Message {
        $contactPhone = SenderIdentity::key($phone);

        if ($contactPhone === null) {
            throw new InvalidArgumentException('Nomor tujuan tidak valid: '.$phone);
        }

        $message = DB::transaction(function () use ($tenantId, $contactPhone, $body, $channel, $providerMessageId, $media, $replyTo): Message {
            $conversation = Conversation::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'contact_phone' => $contactPhone],
                ['channel' => $channel],
            );

            $now = now();

            // Jalur percakapan tidak ditimpa: pesan keluar lewat blast tidak
            // boleh memindahkan balasan berikutnya ke gateway yang lain.
            $conversation->last_message_at = $now;
            $conversation->save();

            $message = $conversation->messages()->create([
                'direction' => MessageDirection::Out,
                'body' => trim($body),
                'provider_message_id' => $providerMessageId,
                'reply_to_message_id' => $replyTo?->id,
                'status' => MessageStatus::Sent,
                'sent_at' => $now,
                ...$media,
            ]);

            app(LogAuditAction::class)->handle(
                'inbox.message_sent',
                $conversation,
                null,
                [
                    'tenant_id' => $tenantId,
                    'new' => $message->attributesToArray(),
                    'channel' => $conversation->channel?->value,
                ],
                sprintf('Mengirim pesan WhatsApp ke %s melalui %s.', $contactPhone, $conversation->channel?->label() ?? $channel->label()),
            );

            return $message;
        });

        // Relasi disisipkan supaya balasan membawa konteks kutipannya di
        // response tanpa kueri tambahan.
        if ($replyTo !== null) {
            $message->setRelation('replyTo', $replyTo);
        }

        $this->broadcastInboxMessage($message, $tenantId);

        return $message;
    }
}
