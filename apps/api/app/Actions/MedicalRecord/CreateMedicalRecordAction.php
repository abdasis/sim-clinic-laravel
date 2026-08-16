<?php

namespace App\Actions\MedicalRecord;

use App\Actions\LogAuditAction;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\User;

/**
 * Tulis rekam medis untuk satu kunjungan yang sudah selesai.
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
            'anamnesis' => $data['anamnesis'] ?? null,
            'skincare_history' => $data['skincare_history'] ?? null,
            'allergy_history' => $data['allergy_history'] ?? null,
        ]);

        app(LogAuditAction::class)->handle(
            'medical_record.created',
            $record,
            $author,
            ['attributes' => $record->getAttributes()],
            'Mengisi rekam medis pasien '.($booking->patient?->name ?? 'pasien').'.',
        );

        return $record;
    }
}
