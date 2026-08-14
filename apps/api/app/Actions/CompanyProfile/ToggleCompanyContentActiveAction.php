<?php

namespace App\Actions\CompanyProfile;

use App\Actions\LogAuditAction;
use App\Support\CompanyContentRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Tampilkan atau sembunyikan satu baris konten dari landing publik tanpa
 * menghapusnya — cara paling sering dipakai admin saat promo berakhir.
 */
class ToggleCompanyContentActiveAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    public function handle(Model $record, string $entity): Model
    {
        $wasActive = (bool) $record->is_active;

        try {
            $record->update(['is_active' => ! $wasActive]);
        } catch (\Throwable $e) {
            Log::error('Gagal mengubah status tampil konten company profile.', [
                'exception' => $e,
                'entity' => $entity,
                'id' => $record->getKey(),
            ]);

            throw $e;
        }

        $this->audit->handle(
            action: 'company_content.toggled',
            subject: $record,
            context: [
                'entity' => $entity,
                'old' => ['is_active' => $wasActive],
                'new' => ['is_active' => ! $wasActive],
            ],
            description: sprintf(
                '%s %s di halaman company profile (id: %s).',
                $wasActive ? 'Menyembunyikan' : 'Menampilkan',
                CompanyContentRegistry::label($entity),
                $record->getKey(),
            ),
        );

        return $record;
    }
}
