<?php

namespace App\Actions\CompanyProfile;

use App\Actions\LogAuditAction;
use App\Support\CompanyContentRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Ubah satu baris konten company profile beserta jejak nilai lama dan baru.
 */
class UpdateCompanyContentAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Model $record, string $entity, array $data): Model
    {
        $before = $record->getAttributes();

        try {
            $record->update($data);
        } catch (\Throwable $e) {
            Log::error('Gagal memperbarui konten company profile.', [
                'exception' => $e,
                'entity' => $entity,
                'id' => $record->getKey(),
                'data' => $data,
            ]);

            throw $e;
        }

        $after = $record->fresh()->getAttributes();

        $this->audit->handle(
            action: 'company_content.updated',
            subject: $record,
            context: [
                'entity' => $entity,
                'old' => array_intersect_key($before, $data),
                'new' => array_intersect_key($after, $data),
            ],
            description: sprintf(
                'Memperbarui %s di halaman company profile (id: %s).',
                CompanyContentRegistry::label($entity),
                $record->getKey(),
            ),
        );

        return $record;
    }
}
