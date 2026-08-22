<?php

namespace App\Actions\MedicalRecord;

use App\Actions\LogAuditAction;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;

/**
 * Tulis rekam medis untuk satu kunjungan yang sudah selesai, atau langsung
 * untuk pasien yang datang tanpa janji (issue #319).
 */
class CreateMedicalRecordAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Booking $booking, User $author, array $data): MedicalRecord
    {
        // Pasien mengikuti kunjungan bila ada; tanpa kunjungan, formulir yang
        // menyebutnya dan FormRequest sudah memastikan ia terisi.
        $patientId = $booking?->patient_id ?? (int) $data['patient_id'];

        $record = MedicalRecord::create([
            'booking_id' => $booking?->id,
            'patient_id' => $patientId,
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
            $this->narrate($booking, $patientId),
        );

        return $record;
    }

    /**
     * Asal-usul catatan ikut disebut: menelusuri riwayat klinis berarti
     * membedakan kunjungan terjadwal dari pasien yang datang langsung.
     */
    private function narrate(?Booking $booking, int $patientId): string
    {
        $name = $booking?->patient?->name
            ?? Patient::query()->whereKey($patientId)->value('name')
            ?? 'pasien';

        return $booking !== null
            ? 'Mengisi rekam medis pasien '.$name.'.'
            : 'Mengisi rekam medis pasien '.$name.' (tanpa kunjungan terjadwal).';
    }
}
