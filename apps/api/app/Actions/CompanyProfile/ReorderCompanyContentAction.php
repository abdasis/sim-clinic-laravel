<?php

namespace App\Actions\CompanyProfile;

use App\Actions\LogAuditAction;
use App\Support\CompanyContentRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Simpan urutan tampil baru. Daftar id dikirim utuh sesuai urutan akhir,
 * lalu `sort_order` ditulis ulang mengikuti posisinya.
 */
class ReorderCompanyContentAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int>  $ids
     */
    public function handle(string $modelClass, string $entity, array $ids): void
    {
        try {
            foreach (array_values($ids) as $position => $id) {
                // TenantScope aktif, jadi id milik tenant lain tidak tersentuh.
                $modelClass::query()
                    ->whereKey($id)
                    ->update(['sort_order' => $position + 1]);
            }
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan urutan konten company profile.', [
                'exception' => $e,
                'entity' => $entity,
                'ids' => $ids,
            ]);

            throw $e;
        }

        $this->audit->handle(
            action: 'company_content.reordered',
            context: ['entity' => $entity, 'new' => ['order' => array_values($ids)]],
            description: sprintf(
                'Mengubah urutan tampil %s di halaman company profile.',
                CompanyContentRegistry::label($entity),
            ),
        );
    }
}
