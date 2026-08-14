<?php

namespace App\Actions\MedicalRecord;

use App\Actions\LogAuditAction;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\User;

/**
 * Tulis rekam medis SOAP untuk satu kunjungan yang sudah selesai.
 */
class CreateMedicalRecordAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Booking $booking, User $author, array $data): MedicalRecord
    {
        $record = MedicalRecord::create([
            'booking_id' => $booking->id,
            'patient_id' => $booking->patient_id,
            'author_id' => $author->id,
            'subjective' => $data['subjective'] ?? null,
            'objective' => $data['objective'] ?? null,
            'assessment' => $data['assessment'] ?? null,
            'plan' => $data['plan'] ?? null,
        ]);

        app(LogAuditAction::class)->handle(
            'medical_record.created',
            $record,
            $author,
            ['attributes' => $record->getAttributes()],
            'Menulis rekam medis untuk '.($booking->patient?->name ?? 'pasien').'.',
        );

        return $record;
    }
}
