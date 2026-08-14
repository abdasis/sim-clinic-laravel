<?php

namespace App\Actions\CompanyProfile;

use App\Actions\LogAuditAction;
use App\Support\CompanyContentRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Tambah satu baris konten company profile. Delapan entitas konten
 * diperlakukan sama oleh CMS, jadi satu Action melayani semuanya.
 */
class CreateCompanyContentAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $data
     */
    public function handle(string $modelClass, string $entity, array $data): Model
    {
        try {
            $record = $modelClass::query()->create($data);
        } catch (\Throwable $e) {
            Log::error('Gagal menambah konten company profile.', [
                'exception' => $e,
                'entity' => $entity,
                'data' => $data,
            ]);

            throw $e;
        }

        $this->audit->handle(
            action: 'company_content.created',
            subject: $record,
            context: ['entity' => $entity, 'attributes' => $record->getAttributes()],
            description: sprintf(
                'Menambahkan %s baru di halaman company profile (id: %s).',
                CompanyContentRegistry::label($entity),
                $record->getKey(),
            ),
        );

        return $record;
    }
}
