<?php

namespace App\Actions\Patient;

use App\Actions\LogAuditAction;
use App\Models\Patient;

/**
 * Daftarkan pasien baru ke klinik.
 */
class CreatePatientAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Patient
    {
        $patient = Patient::create($attributes);

        app(LogAuditAction::class)->handle(
            'patient.created',
            $patient,
            auth()->user(),
            ['attributes' => $patient->fresh()->getAttributes()],
            'Membuat pasien '.$patient->name.'.',
        );

        return $patient;
    }
}
