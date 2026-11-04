<?php

namespace App\Actions\Chatbot;

use App\Actions\LogAuditAction;
use App\Models\ChatMessage;
use App\Models\WhatsappSetting;
use App\Support\WhatsappGatewayClient;
use App\Support\WhatsAppText;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    public function handle(
        WhatsappGatewayClient $client,
        WhatsappSetting $setting,
        string $senderPhone,
        string $message,
    ): ?ChatMessage {
        $text = WhatsAppText::normalize($message);

        try {
            $client->sendText($setting, $senderPhone, $text, 'chatbot-closing-'.Str::uuid());
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
