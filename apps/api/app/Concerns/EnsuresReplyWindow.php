<?php

namespace App\Concerns;

use App\Enums\ConversationChannel;
use App\Enums\MessageDirection;
use App\Models\Conversation;
use Illuminate\Validation\ValidationException;

/**
 * Balasan bebas Cloud API hanya berlaku 24 jam sejak pesan terakhir kontak.
 * Lewat dari itu Meta menolak, dan penolakannya baru terlihat setelah isi
 * pesannya disusun — jadi diperiksa lebih dulu.
 */
trait EnsuresReplyWindow
{
    private function ensureReplyWindowOpen(Conversation $conversation, string $field = 'body'): void
    {
        if ($conversation->channel !== ConversationChannel::CloudApi) {
            return;
        }

        $lastInbound = $conversation->messages()
            ->where('direction', MessageDirection::In)
            ->orderByDesc('sent_at')
            ->first();

        if ($lastInbound?->sent_at === null || $lastInbound->sent_at->lt(now()->subDay())) {
            throw ValidationException::withMessages([$field => __('inbox.window_expired')]);
        }
    }
}
