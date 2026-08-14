<?php

namespace App\Actions\MedicalRecord;

use App\Actions\LogAuditAction;
use App\Models\MedicalRecord;
use App\Models\TreatmentRecord;

/**
 * Catat tindakan yang dilakukan pada satu rekam medis. Nama layanan
 * disimpan sebagai snapshot supaya perubahan master tidak mengubah riwayat.
 */
class AddTreatmentRecordAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(MedicalRecord $record, array $data, ?string $serviceName): TreatmentRecord
    {
        $treatment = TreatmentRecord::create([
            'medical_record_id' => $record->id,
            'service_id' => $data['service_id'] ?? null,
            'service_name' => $serviceName,
            'notes' => $data['notes'] ?? null,
        ]);

        app(LogAuditAction::class)->handle(
            'medical_record.treatment_added',
            $record,
            auth()->user(),
            ['attributes' => $treatment->getAttributes()],
            'Menambahkan tindakan '.($serviceName ?? 'tanpa nama').' pada rekam medis '
                .($record->patient?->name ?? 'pasien').'.',
        );

        return $treatment;
    }
}
