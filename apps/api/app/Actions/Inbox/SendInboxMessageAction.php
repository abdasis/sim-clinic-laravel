<?php

namespace App\Actions\Inbox;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappSetting;
use App\Support\WhatsappGatewayClient;
use App\Support\WhatsappGatewayException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kirim satu balasan teks pada sebuah percakapan, lalu catat di utasnya.
 *
 * Gateway internal adalah satu-satunya jalur keluar sekarang; kanal pada
 * percakapan hanya menyimpan asal pesan masuk. Kutipan tetap disimpan untuk
 * data lama, tetapi gateway tidak menerima id pesan yang dikutip.
 */
class SendInboxMessageAction
{
    public function handle(Conversation $conversation, string $body, ?int $replyToMessageId = null): Message
    {
        $replyTo = $replyToMessageId === null
            ? null
            : $conversation->messages()->whereKey($replyToMessageId)->first();

        if ($replyToMessageId !== null && $replyTo === null) {
            throw ValidationException::withMessages(['reply_to_message_id' => __('inbox.reply_not_found')]);
        }

        try {
            $providerMessageId = app(WhatsappGatewayClient::class)->sendText(
                $this->setting($conversation),
                $conversation->contact_phone,
                $body,
                'inbox-'.Str::uuid(),
            );
        } catch (WhatsappGatewayException $e) {
            Log::error('Gagal mengirim balasan kotak masuk WhatsApp lewat gateway.', [
                'exception' => $e,
                'conversation_id' => $conversation->id,
                'tenant_id' => $conversation->tenant_id,
            ]);

            throw ValidationException::withMessages(['body' => __('inbox.send_failed')]);
        }

        return DB::transaction(fn (): Message => app(RecordOutboundMessageAction::class)->handle(
            $conversation->tenant_id,
            $conversation->contact_phone,
            $body,
            $conversation->channel,
            $providerMessageId,
            [],
            $replyTo,
        ));
    }

    private function setting(Conversation $conversation): WhatsappSetting
    {
        $setting = WhatsappSetting::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->first();

        if ($setting === null) {
            throw ValidationException::withMessages(['body' => __('inbox.gateway_not_ready')]);
        }

        return $setting;
    }
}
