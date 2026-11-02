<?php

namespace App\Events;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Perubahan status kirim sebuah pesan (terkirim, diterima, dibaca, gagal).
 *
 * Nama siarannya sengaja sama dengan pesan baru: pendengar di layar klinik
 * cukup menyegarkan utas yang sama, tanpa cabang penanganan tersendiri.
 */
class InboxMessageStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly int $messageId,
        public readonly MessageDirection $direction,
        public readonly MessageStatus $status,
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
            'status' => $this->status->value,
        ];
    }
}
