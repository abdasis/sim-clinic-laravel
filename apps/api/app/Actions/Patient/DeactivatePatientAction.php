<?php

namespace App\Actions\Patient;

use App\Actions\LogAuditAction;
use App\Models\Patient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Nonaktifkan pasien lewat soft delete; booking, rekam medis, dan transaksi
 * yang pernah dibuat tetap merujuk pasien ini.
 */
class DeactivatePatientAction
{
    public function handle(Patient $patient): Patient
    {
        try {
            $patient->delete();
        } catch (Throwable $e) {
            Log::error('Gagal menonaktifkan pasien.', [
                'exception' => $e,
                'patient_id' => $patient->id,
                'tenant_id' => $patient->tenant_id,
            ]);

            throw $e;
        }

        app(LogAuditAction::class)->handle(
            'patient.deactivated',
            $patient,
            auth()->user(),
            ['old' => ['deleted_at' => null], 'new' => ['deleted_at' => $patient->deleted_at?->toIso8601String()]],
            'Menonaktifkan pasien '.$patient->name.'.',
        );

        return $patient;
    }
}
