<?php

namespace App\Actions\CompanyProfile;

use App\Actions\LogAuditAction;
use App\Support\CompanyContentRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Hapus satu baris konten company profile. Konten pemasaran tidak punya
 * nilai riwayat seperti rekam medis, jadi penghapusannya permanen — jejaknya
 * tetap tersimpan di activity log lengkap dengan isi terakhirnya.
 */
class DeleteCompanyContentAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    public function handle(Model $record, string $entity): void
    {
        $attributes = $record->getAttributes();
        $key = $record->getKey();

        try {
            $record->delete();
        } catch (\Throwable $e) {
            Log::error('Gagal menghapus konten company profile.', [
                'exception' => $e,
                'entity' => $entity,
                'id' => $key,
            ]);

            throw $e;
        }

        $this->audit->handle(
            action: 'company_content.deleted',
            context: ['entity' => $entity, 'id' => $key, 'attributes' => $attributes],
            description: sprintf(
                'Menghapus %s dari halaman company profile (id: %s).',
                CompanyContentRegistry::label($entity),
                $key,
            ),
        );
    }
}
