<?php

namespace App\Concerns;

use App\Events\InboxMessageCreated;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Siarkan pesan kotak masuk yang baru tercatat ke layar klinik.
 *
 * Dipanggil setelah transaksi pencatatan selesai: pesan yang sudah tersimpan
 * tetap tersimpan walau siarannya gagal, dan kegagalan itu dicatat alih-alih
 * diteruskan ke webhook gateway yang lalu akan mengulang kiriman.
 */
trait BroadcastsInboxMessage
{
    protected function broadcastInboxMessage(Message $message, int $tenantId): void
    {
        try {
            event(new InboxMessageCreated(
                tenantId: $tenantId,
                conversationId: (int) $message->conversation_id,
                messageId: (int) $message->id,
                direction: $message->direction,
            ));
        } catch (Throwable $e) {
            Log::error('Gagal menyiarkan pesan kotak masuk baru.', [
                'exception' => $e,
                'tenant_id' => $tenantId,
                'message_id' => $message->id,
            ]);
        }
    }
}
