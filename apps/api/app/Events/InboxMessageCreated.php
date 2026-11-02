<?php

namespace App\Events;

use App\Enums\MessageDirection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pesan kotak masuk baru yang perlu muncul di layar staf tanpa menunggu
 * penyegaran halaman.
 *
 * Isinya sengaja hanya id dan arah, bukan model: pendengar yang butuh isi
 * lengkap mengambilnya sendiri lewat API, dan model yang diserialisasi akan
 * membawa kolom yang tidak perlu disiarkan.
 */
class InboxMessageCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly int $messageId,
        public readonly MessageDirection $direction,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('tenant.'.$this->tenantId.'.inbox');
    }

    public function broadcastAs(): string
    {
        return 'inbox.message';
    }

    /** @return array<string, int|string> */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'direction' => $this->direction->value,
        ];
    }
}
