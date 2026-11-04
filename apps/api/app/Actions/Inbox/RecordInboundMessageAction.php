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
 * Catat satu pesan yang masuk dari kontak menjadi utas kotak masuk.
 *
 * Idempoten terhadap `provider_message_id`: gateway yang tidak dibalas 200
 * akan mengirim ulang pesannya, dan pengulangan itu tidak boleh menambah
 * penghitung belum dibaca dua kali. Pemanggil memakai `created` untuk tahu
 * apakah pesan ini baru; kiriman ulang tidak boleh memicu balasan kedua.
 */
class RecordInboundMessageAction
{
    use BroadcastsInboxMessage;

    /**
     * @return array{message: Message, created: bool}
     */
    public function handle(
        int $tenantId,
        string $phone,
        string $body,
        ConversationChannel $channel,
        ?string $providerMessageId = null,
        ?string $contactName = null,
    ): array {
        $contactPhone = SenderIdentity::key($phone);

        if ($contactPhone === null) {
            throw new InvalidArgumentException('Nomor pengirim tidak valid: '.$phone);
        }

        /** @var array{message: Message, created: bool} $result */
        $result = DB::transaction(function () use ($tenantId, $contactPhone, $body, $channel, $providerMessageId, $contactName): array {
            $conversation = Conversation::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'contact_phone' => $contactPhone],
                ['channel' => $channel, 'last_message_at' => now()],
            );

            if ($providerMessageId !== null) {
                $existing = Message::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('provider_message_id', $providerMessageId)
                    ->first();

                // Kiriman ulang gateway bukan pesan baru: tidak boleh menambah
                // penghitung maupun menyiarkan notifikasi kedua kalinya.
                if ($existing !== null) {
                    return ['message' => $existing, 'created' => false];
                }
            }

            $now = now();

            if (filled($contactName)) {
                $conversation->contact_name = $contactName;
            }

            $conversation->channel = $channel;
            $conversation->last_message_at = $now;
            $conversation->save();

            // Dinaikkan di basis data, bukan dibaca lalu ditulis: dua pesan yang
            // tiba hampir bersamaan tidak saling menimpa hitungannya.
            $conversation->increment('unread_count');

            $message = $conversation->messages()->create([
                'direction' => MessageDirection::In,
                'body' => trim($body),
                'provider_message_id' => $providerMessageId,
                'status' => MessageStatus::Sent,
                'sent_at' => $now,
            ]);

            app(LogAuditAction::class)->handle(
                'inbox.message_received',
                $conversation,
                null,
                [
                    'tenant_id' => $tenantId,
                    'new' => $message->attributesToArray(),
                    'channel' => $channel->value,
                    'unread_count' => $conversation->unread_count,
                ],
                sprintf(
                    'Menerima pesan WhatsApp dari %s%s melalui %s.',
                    $contactPhone,
                    filled($contactName) ? ' ('.$contactName.')' : '',
                    $channel->label(),
                ),
            );

            return ['message' => $message, 'created' => true];
        });

        if ($result['created']) {
            $this->broadcastInboxMessage($result['message'], $tenantId);
        }

        return $result;
    }
}
