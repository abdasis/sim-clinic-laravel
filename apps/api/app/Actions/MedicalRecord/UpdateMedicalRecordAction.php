<?php

namespace App\Actions\MedicalRecord;

use App\Actions\LogAuditAction;
use App\Models\MedicalRecord;

/**
 * Perbarui isi SOAP rekam medis. Pasien dan kunjungannya tidak ikut berubah
 * — keduanya menentukan milik siapa catatan ini.
 */
class UpdateMedicalRecordAction
{
    private const SOAP_FIELDS = ['subjective', 'objective', 'assessment', 'plan'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(MedicalRecord $record, array $data): MedicalRecord
    {
        $changes = array_intersect_key($data, array_flip(self::SOAP_FIELDS));

        $old = $record->only(array_keys($changes));

        $record->update($changes);

        app(LogAuditAction::class)->handle(
            'medical_record.updated',
            $record,
            auth()->user(),
            ['old' => $old, 'new' => $record->only(array_keys($changes))],
            'Memperbarui rekam medis pasien '.($record->patient?->name ?? 'pasien').'.',
        );

        return $record;
    }
}
