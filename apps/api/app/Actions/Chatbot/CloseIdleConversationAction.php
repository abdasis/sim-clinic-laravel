<?php

namespace App\Actions\Chatbot;

use App\Actions\LogAuditAction;
use App\Models\ChatMessage;
use App\Support\WahaClient;
use App\Support\WhatsAppText;
use Illuminate\Support\Facades\Log;

/**
 * Kirim satu pesan penutup lalu tandai percakapannya sudah ditutup.
 *
 * Penandanya menempel di pesan penutup itu sendiri, bukan di tabel terpisah:
 * yang perlu diketahui putaran berikutnya cuma "pesan terakhir ini penutup
 * atau bukan", dan itu sudah terjawab oleh barisnya sendiri.
 */
class CloseIdleConversationAction
{
    public function __construct(
        private readonly SaveChatMessageAction $messages,
        private readonly LogAuditAction $audit,
    ) {}

    public function handle(WahaClient $waha, string $senderPhone, string $message): ?ChatMessage
    {
        $text = WhatsAppText::normalize($message);

        try {
            $waha->send($senderPhone, $text);
        } catch (\Throwable $e) {
            // Satu nomor yang gagal tidak boleh menghentikan sisanya, jadi
            // kegagalannya dicatat dan dilaporkan sebagai "tidak jadi" —
            // bukan dilempar ke atas.
            Log::error('Gagal mengirim pesan penutup percakapan chatbot.', [
                'exception' => $e,
                'sender_phone' => $senderPhone,
            ]);

            return null;
        }

        $saved = $this->messages->handle($senderPhone, 'out', $text, 'assistant');
        $saved->forceFill(['is_closing' => true])->save();

        $this->audit->handle(
            action: 'chatbot.conversation_closed',
            subject: $saved,
            context: [
                'attributes' => [
                    'sender_phone' => $senderPhone,
                    'content' => $text,
                    'is_closing' => true,
                ],
            ],
            description: sprintf(
                'Menutup percakapan chatbot dengan %s karena tidak ada balasan, dan mengirim pesan penutupnya.',
                $senderPhone,
            ),
        );

        return $saved;
    }
}
