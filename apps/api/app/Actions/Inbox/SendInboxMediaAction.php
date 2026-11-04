<?php

namespace App\Actions\Inbox;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappSetting;
use App\Support\WhatsappGatewayClient;
use App\Support\WhatsappGatewayException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kirim satu lampiran (gambar, video, audio, dokumen, stiker) pada percakapan,
 * lalu catat di utasnya.
 *
 * Berkas disimpan dulu supaya riwayatnya bisa dibuka lagi dari kotak masuk dan
 * supaya gateway bisa mengambilnya lewat URL publik. Bila gateway menolak
 * setelah berkas tersimpan, berkas itu dihapus lagi: lampiran yang tidak
 * pernah terkirim tidak boleh menumpuk di disk.
 */
class SendInboxMediaAction
{
    public function handle(
        Conversation $conversation,
        string $type,
        UploadedFile $file,
        ?string $caption = null,
    ): Message {
        $setting = $this->setting($conversation);

        $directory = "inbox-media/{$conversation->tenant_id}/{$conversation->id}";
        $path = $file->store($directory, 'public');

        if ($path === false) {
            throw ValidationException::withMessages(['file' => __('inbox.media_store_failed')]);
        }

        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $filename = $file->getClientOriginalName();
        $url = Storage::disk('public')->url($path);

        try {
            $providerMessageId = $this->deliver($setting, $conversation, $type, $url, $filename, $caption);
        } catch (WhatsappGatewayException $e) {
            Storage::disk('public')->delete($path);

            Log::error('Gagal mengirim lampiran kotak masuk WhatsApp lewat gateway.', [
                'exception' => $e,
                'conversation_id' => $conversation->id,
                'tenant_id' => $conversation->tenant_id,
                'media_type' => $type,
            ]);

            throw ValidationException::withMessages(['file' => __('inbox.send_failed')]);
        } catch (ValidationException $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        $body = $caption !== null && trim($caption) !== '' ? trim($caption) : $filename;

        return app(RecordOutboundMessageAction::class)->handle(
            $conversation->tenant_id,
            $conversation->contact_phone,
            $body,
            $conversation->channel,
            $providerMessageId,
            [
                'media_type' => $type,
                'media_path' => $path,
                'media_mime' => $mime,
                'media_name' => $filename,
            ],
        );
    }

    /** @return string|null id pesan dari gateway, bila ia memberikannya */
    private function deliver(
        WhatsappSetting $setting,
        Conversation $conversation,
        string $type,
        string $url,
        string $filename,
        ?string $caption,
    ): ?string {
        $client = app(WhatsappGatewayClient::class);
        $phone = $conversation->contact_phone;
        $key = 'inbox-'.Str::uuid();

        return match ($type) {
            'image' => $client->sendImage($setting, $phone, $url, $caption, $key),
            'audio' => $client->sendAudio($setting, $phone, $url, 'false', $key),
            // Video dan stiker ikut jalur dokumen: gateway hanya membedakan
            // gambar, audio, dan dokumen.
            default => $client->sendDocument($setting, $phone, $url, $filename, $caption, $key),
        };
    }

    private function setting(Conversation $conversation): WhatsappSetting
    {
        $setting = WhatsappSetting::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->first();

        if ($setting === null) {
            throw ValidationException::withMessages(['file' => __('inbox.gateway_not_ready')]);
        }

        return $setting;
    }
}
