<?php

namespace App\Actions\CompanyProfile;

use App\Actions\LogAuditAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Simpan berkas media company profile ke disk publik. Path-nya dipisah per
 * tenant supaya berkas satu klinik tidak tercampur dengan klinik lain.
 */
class UploadCompanyMediaAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    public function handle(int $tenantId, string $entity, UploadedFile $file): string
    {
        try {
            $path = $file->store("company-profile/{$tenantId}/{$entity}", 'public');
        } catch (\Throwable $e) {
            Log::error('Gagal mengunggah media company profile.', [
                'exception' => $e,
                'tenant_id' => $tenantId,
                'entity' => $entity,
                'file' => $file->getClientOriginalName(),
            ]);

            throw $e;
        }

        $this->audit->handle(
            action: 'company_media.uploaded',
            context: [
                'entity' => $entity,
                'attributes' => [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ],
            ],
            description: sprintf('Mengunggah berkas media company profile (%s).', $path),
        );

        return $path;
    }
}
