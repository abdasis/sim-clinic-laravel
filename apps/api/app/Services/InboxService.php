<?php

namespace App\Services;

use App\Actions\Inbox\MarkConversationReadAction;
use App\Actions\Inbox\SendInboxContactAction;
use App\Actions\Inbox\SendInboxMediaAction;
use App\Actions\Inbox\SendInboxMessageAction;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\UploadedFile;

/**
 * Orkestrasi kotak masuk WhatsApp: mengirim balasan teks, lampiran, kontak,
 * dan menandai percakapan sudah dibaca. Daftar dan detail dibaca langsung
 * oleh controller.
 */
class InboxService
{
    public function send(Conversation $conversation, string $body, ?int $replyToMessageId = null): Message
    {
        return app(SendInboxMessageAction::class)->handle($conversation, $body, $replyToMessageId);
    }

    public function sendMedia(Conversation $conversation, string $type, UploadedFile $file, ?string $caption): Message
    {
        return app(SendInboxMediaAction::class)->handle($conversation, $type, $file, $caption);
    }

    public function sendContact(Conversation $conversation, string $name, string $phone): Message
    {
        return app(SendInboxContactAction::class)->handle($conversation, $name, $phone);
    }

    public function markRead(Conversation $conversation): Conversation
    {
        return app(MarkConversationReadAction::class)->handle($conversation);
    }
}
