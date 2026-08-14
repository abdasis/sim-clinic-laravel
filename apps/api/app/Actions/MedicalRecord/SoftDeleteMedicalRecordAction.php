<?php

namespace App\Actions\MedicalRecord;

use App\Actions\LogAuditAction;
use App\Models\MedicalRecord;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hapus rekam medis dari daftar aktif tanpa membuang isinya — tindakan dan
 * foto klinis yang menempel tetap utuh dan bisa ditelusuri.
 */
class SoftDeleteMedicalRecordAction
{
    public function handle(MedicalRecord $record): MedicalRecord
    {
        try {
            $record->delete();
        } catch (Throwable $e) {
            Log::error('Gagal menghapus rekam medis.', [
                'exception' => $e,
                'medical_record_id' => $record->id,
                'tenant_id' => $record->tenant_id,
            ]);

            throw $e;
        }

        app(LogAuditAction::class)->handle(
            'medical_record.deleted',
            $record,
            auth()->user(),
            [
                'old' => ['deleted_at' => null],
                'new' => ['deleted_at' => $record->deleted_at?->toIso8601String()],
            ],
            'Menghapus rekam medis pasien '.($record->patient?->name ?? 'pasien').'.',
        );

        return $record;
    }
}
