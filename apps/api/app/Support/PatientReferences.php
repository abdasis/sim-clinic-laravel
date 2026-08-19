<?php

namespace App\Support;

/**
 * Jejak yang ditinggalkan seorang pasien di data klinik. Yang boleh
 * benar-benar hilang hanya pendaftaran yang salah ketik dan belum sempat
 * dipakai; sisanya membawa rekam medis dan nota yang harus tetap ada.
 */
class PatientReferences extends ModelReferences
{
    protected function sources(): array
    {
        return [
            ['table' => 'bookings', 'column' => 'patient_id'],
            ['table' => 'medical_records', 'column' => 'patient_id'],
            ['table' => 'transactions', 'column' => 'patient_id'],
            ['table' => 'broadcast_recipients', 'column' => 'patient_id'],
        ];
    }
}
