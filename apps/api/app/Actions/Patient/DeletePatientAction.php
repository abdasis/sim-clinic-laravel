<?php

namespace App\Actions\Patient;

use App\Actions\LogAuditAction;
use App\Models\Patient;
use App\Support\PatientReferences;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hapus data pasien secara permanen.
 *
 * Hanya untuk pendaftaran yang belum meninggalkan jejak apa pun — salah ketik
 * atau duplikat yang belum sempat dipakai. Pasien yang sudah pernah datang
 * punya rekam medis dan nota; membuangnya berarti membuang catatan medis dan
 * riwayat keuangan yang harus tetap ada.
 */
class DeletePatientAction
{
    public function handle(Patient $patient): void
    {
        if (app(PatientReferences::class)->has($patient->id)) {
            abort(422, __('patient.has_history'));
        }

        $attributes = $patient->only(['name', 'phone', 'whatsapp', 'gender', 'address']);

        try {
            $patient->forceDelete();
        } catch (Throwable $e) {
            Log::error('Gagal menghapus pasien.', [
                'exception' => $e,
                'patient_id' => $patient->id,
                'tenant_id' => $patient->tenant_id,
            ]);

            throw $e;
        }

        app(LogAuditAction::class)->handle(
            'patient.deleted',
            $patient,
            auth()->user(),
            ['attributes' => $attributes],
            'Menghapus data pasien '.$attributes['name'].'. Pasien ini belum pernah punya booking, rekam medis, maupun nota.',
        );
    }
}
