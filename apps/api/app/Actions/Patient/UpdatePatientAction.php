<?php

namespace App\Actions\Patient;

use App\Actions\LogAuditAction;
use App\Models\Patient;

/**
 * Perbarui data pasien; audit menyimpan nilai lama dan baru.
 */
class UpdatePatientAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Patient $patient, array $attributes): Patient
    {
        $old = $patient->only(array_keys($attributes));

        $patient->update($attributes);

        app(LogAuditAction::class)->handle(
            'patient.updated',
            $patient->fresh(),
            auth()->user(),
            ['old' => $old, 'new' => $patient->fresh()->only(array_keys($attributes))],
            'Memperbarui pasien '.$patient->name.'.',
        );

        return $patient;
    }
}
