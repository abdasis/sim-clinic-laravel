<?php

namespace App\Actions\Chatbot;

use App\Models\ChatMessage;

/**
 * Simpan satu baris percakapan chatbot.
 *
 * Sengaja tidak ikut mencatat audit log: percakapan bisa puluhan baris per
 * pasien per hari, dan menyalinnya ke log aktivitas hanya akan menenggelamkan
 * kejadian yang benar-benar perlu ditinjau admin. Yang dicatat di audit log
 * adalah akibatnya — booking yang jadi.
 */
class SaveChatMessageAction
{
    public function handle(
        string $senderPhone,
        string $direction,
        string $content,
        ?string $role = null,
        ?string $toolName = null,
        ?string $toolCallId = null,
    ): ChatMessage {
        return ChatMessage::create([
            'sender_phone' => $senderPhone,
            'direction' => $direction,
            'content' => $content,
            'role' => $role,
            'tool_name' => $toolName,
            'tool_call_id' => $toolCallId ?: null,
        ]);
    }
}
