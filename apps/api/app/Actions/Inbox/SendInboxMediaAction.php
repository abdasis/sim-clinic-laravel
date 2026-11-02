<?php

namespace App\Actions\Inbox;

use App\Concerns\EnsuresReplyWindow;
use App\Enums\ConversationChannel;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\CloudApiClient;
use App\Support\CloudApiException;
use App\Support\WahaClient;
use App\Support\WahaException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Kirim satu lampiran (gambar, video, audio, dokumen, stiker) pada percakapan,
 * lalu catat di utasnya.
 *
 * Berkas disimpan dulu supaya riwayatnya bisa dibuka lagi dari kotak masuk.
 * Bila penyedia menolak setelah berkas tersimpan, berkas itu dihapus lagi:
 * lampiran yang tidak pernah terkirim tidak boleh menumpuk di disk.
 */
class SendInboxMediaAction
{
    use EnsuresReplyWindow;

    public function handle(
        Conversation $conversation,
        string $type,
        UploadedFile $file,
        ?string $caption = null,
    ): Message {
        $this->ensureReplyWindowOpen($conversation, 'file');

        $directory = "inbox-media/{$conversation->tenant_id}/{$conversation->id}";
        $path = $file->store($directory, 'public');

        if ($path === false) {
            throw ValidationException::withMessages(['file' => __('inbox.media_store_failed')]);
        }

        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $filename = $file->getClientOriginalName();

        try {
            $providerMessageId = $this->deliver($conversation, $type, (string) $file->getRealPath(), $mime, $filename, $caption);
        } catch (CloudApiException|WahaException $e) {
            Storage::disk('public')->delete($path);

            Log::error('Gagal mengirim lampiran kotak masuk WhatsApp.', [
                'exception' => $e,
                'conversation_id' => $conversation->id,
                'channel' => $conversation->channel?->value,
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

    /** @return string|null id pesan dari penyedia, bila ia memberikannya */
    private function deliver(
        Conversation $conversation,
        string $type,
        string $absolutePath,
        string $mime,
        string $filename,
        ?string $caption,
    ): ?string {
        $binary = (string) file_get_contents($absolutePath);

        if ($conversation->channel === ConversationChannel::CloudApi) {
            $client = app(CloudApiClient::class);

            if (! $client instanceof CloudApiClient) {
                throw ValidationException::withMessages(['file' => __('inbox.cloud_api_not_ready')]);
            }

            $mediaId = $client->uploadMedia($binary, $mime, $filename);

            return $client->sendMedia($conversation->contact_phone, $type, $mediaId, $caption, $filename);
        }

        $client = app(WahaClient::class);

        if (! $client instanceof WahaClient) {
            throw ValidationException::withMessages(['file' => __('inbox.waha_not_ready')]);
        }

        $client->sendFile($conversation->contact_phone, $binary, $mime, $filename, $caption);

        return null;
    }
}
