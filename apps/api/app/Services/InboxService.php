<?php

namespace App\Services;

use App\Actions\Inbox\MarkConversationReadAction;
use App\Actions\Inbox\SendInboxMessageAction;
use App\Models\Conversation;
use App\Models\Message;

/**
 * Orkestrasi kotak masuk WhatsApp: mengirim balasan dan menandai percakapan
 * sudah dibaca. Daftar dan detail dibaca langsung oleh controller.
 */
class InboxService
{
    public function send(Conversation $conversation, string $body): Message
    {
        return app(SendInboxMessageAction::class)->handle($conversation, $body);
    }

    public function markRead(Conversation $conversation): Conversation
    {
        return app(MarkConversationReadAction::class)->handle($conversation);
    }
}
