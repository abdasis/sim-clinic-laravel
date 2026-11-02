<?php

namespace App\Actions\Inbox;

use App\Actions\LogAuditAction;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;

/**
 * Nol-kan penghitung belum dibaca pada satu percakapan.
 */
class MarkConversationReadAction
{
    public function handle(Conversation $conversation): Conversation
    {
        $previous = (int) $conversation->unread_count;

        if ($previous === 0) {
            return $conversation;
        }

        return DB::transaction(function () use ($conversation, $previous): Conversation {
            $conversation->update(['unread_count' => 0]);

            app(LogAuditAction::class)->handle(
                'inbox.conversation_read',
                $conversation,
                null,
                [
                    'tenant_id' => $conversation->tenant_id,
                    'old' => ['unread_count' => $previous],
                    'new' => ['unread_count' => 0],
                ],
                sprintf('Menandai percakapan dengan %s sudah dibaca.', $conversation->contact_phone),
            );

            return $conversation;
        });
    }
}
