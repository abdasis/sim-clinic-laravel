<?php

namespace App\Actions\Inbox;

use App\Actions\LogAuditAction;
use App\Concerns\BroadcastsInboxMessage;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

/**
 * Perbarui status kirim satu pesan dari webhook status Meta.
 *
 * Statusnya hanya boleh maju (terkirim → diterima → dibaca); kiriman ulang
 * webhook yang datang terlambat tidak boleh memundurkan centang yang sudah
 * biru. `failed` masih boleh menggantikan `terkirim`, tapi tidak pernah
 * menimpa pesan yang sudah diterima atau dibaca.
 */
class UpdateMessageStatusAction
{
    use BroadcastsInboxMessage;

    public function handle(int $tenantId, string $providerMessageId, MessageStatus $status): ?Message
    {
        // Dicari lewat percakapan pemilik tenant, bukan id pesan penyedia saja:
        // id dari Meta tidak dijamin unik antar-klinik.
        $message = Message::query()
            ->where('provider_message_id', $providerMessageId)
            ->where('direction', MessageDirection::Out)
            ->whereHas('conversation', fn ($query) => $query->where('tenant_id', $tenantId))
            ->first();

        if ($message === null) {
            Log::debug('Status pesan gateway diabaikan: pesan tidak ditemukan.', [
                'tenant_id' => $tenantId,
                'provider_message_id' => $providerMessageId,
                'status' => $status->value,
            ]);

            return null;
        }

        $current = $message->status ?? MessageStatus::Sent;

        // Pengulangan webhook tidak menulis ulang maupun menyiarkan ulang.
        if ($current === $status || ! $this->shouldAdvance($current, $status)) {
            return $message;
        }

        $message->status = $status;
        $message->save();

        app(LogAuditAction::class)->handle(
            'inbox.message_status_updated',
            $message->conversation,
            null,
            [
                'tenant_id' => $tenantId,
                'old' => ['status' => $current->value],
                'new' => ['status' => $status->value],
                'message_id' => $message->id,
            ],
            sprintf(
                'Status pesan WhatsApp diperbarui dari "%s" menjadi "%s".',
                $current->label(),
                $status->label(),
            ),
        );

        $this->broadcastInboxMessageStatus($message, $tenantId);

        return $message;
    }

    private function shouldAdvance(MessageStatus $current, MessageStatus $next): bool
    {
        if ($next === MessageStatus::Failed) {
            return ! in_array($current, [MessageStatus::Delivered, MessageStatus::Read], true);
        }

        return $this->rank($next) > $this->rank($current);
    }

    private function rank(MessageStatus $status): int
    {
        return match ($status) {
            MessageStatus::Failed => -1,
            MessageStatus::Sent => 0,
            MessageStatus::Delivered => 1,
            MessageStatus::Read => 2,
        };
    }
}
