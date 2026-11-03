<?php

namespace App\Services;

use App\Actions\LogAuditAction;
use App\Support\CloudApiClient;
use App\Support\CloudApiException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengelolaan template resmi Meta dari dalam aplikasi: daftar, daftarkan
 * baru, dan hapus. Semuanya panggilan API eksternal, bukan data lokal, jadi
 * tidak ada Action yang menyentuh basis data.
 */
class WhatsappTemplateService
{
    /**
     * Semua template beserta status peninjauannya. Kegagalan Cloud API bukan
     * galat halaman: layar cukup menampilkan "belum siap" lalu admin bisa
     * memperbaiki pengaturan WABA.
     *
     * @return array{templates: array<int, array<string, mixed>>, ready: bool}
     */
    public function all(): array
    {
        $client = app(CloudApiClient::class);

        if (! $client instanceof CloudApiClient) {
            return ['templates' => [], 'ready' => false];
        }

        try {
            return ['templates' => $client->allTemplates(), 'ready' => true];
        } catch (Throwable $e) {
            Log::error('Gagal mengambil daftar template WhatsApp Cloud API.', [
                'exception' => $e,
            ]);

            return ['templates' => [], 'ready' => false];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> template hasil normalisasi
     */
    public function create(array $data): array
    {
        $client = $this->client();
        $headerType = $data['header_type'] ?? null;

        try {
            $template = $client->createTemplate(
                (string) $data['name'],
                (string) $data['language'],
                (string) $data['category'],
                (string) $data['body'],
                is_string($headerType) ? $headerType : null,
                is_string($data['header_text'] ?? null) ? $data['header_text'] : null,
            );
        } catch (CloudApiException $e) {
            Log::error('Cloud API Meta menolak pembuatan template WhatsApp.', [
                'template' => $data['name'],
                'status' => $e->status,
                'reason' => $e->reason,
            ]);

            abort(422, __('broadcast.template_create_failed', [
                'reason' => $e->reason ?? $e->getMessage(),
            ]));
        }

        app(LogAuditAction::class)->handle(
            'broadcast.template_created',
            null,
            Auth::user(),
            [
                'new' => [
                    'name' => $template['name'],
                    'language' => $template['language'],
                    'category' => $template['category'],
                    'header_type' => $template['header_type'],
                    'status' => $template['status'],
                ],
            ],
            'Membuat template WhatsApp "'.$template['name'].'" ('.$template['language'].', '.$template['category'].', status '.$template['status'].').',
        );

        return $template;
    }

    public function delete(string $name, ?string $hsmId = null): void
    {
        $client = $this->client();

        try {
            $client->deleteTemplate($name, $hsmId);
        } catch (CloudApiException $e) {
            Log::error('Cloud API Meta menolak penghapusan template WhatsApp.', [
                'template' => $name,
                'hsm_id' => $hsmId,
                'status' => $e->status,
                'reason' => $e->reason,
            ]);

            abort(422, __('broadcast.template_delete_failed', [
                'reason' => $e->reason ?? $e->getMessage(),
            ]));
        }

        app(LogAuditAction::class)->handle(
            'broadcast.template_deleted',
            null,
            Auth::user(),
            [
                'old' => [
                    'name' => $name,
                    'hsm_id' => $hsmId,
                ],
            ],
            'Menghapus template WhatsApp "'.$name.'".',
        );
    }

    private function client(): CloudApiClient
    {
        $client = app(CloudApiClient::class);

        if (! $client instanceof CloudApiClient) {
            abort(422, __('broadcast.template_waba_missing'));
        }

        return $client;
    }
}
