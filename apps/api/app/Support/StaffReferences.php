<?php

namespace App\Support;

/**
 * Jejak yang ditinggalkan seorang staf di data klinik.
 *
 * audit_logs sengaja tidak dihitung: kolomnya null saat penghapusan dan narasi
 * lognya sudah memuat nama, jadi jejak sistemnya tidak hilang. Kalau ikut
 * dihitung, staf yang sekadar pernah login pun tidak akan pernah bisa dihapus.
 */
class StaffReferences extends ModelReferences
{
    protected function sources(): array
    {
        return [
            ['table' => 'bookings', 'column' => 'assignee_id'],
            ['table' => 'medical_records', 'column' => 'author_id'],
            ['table' => 'transactions', 'column' => 'cashier_id'],
            ['table' => 'transaction_performers', 'column' => 'user_id'],
            ['table' => 'transaction_items', 'column' => 'offered_by'],
            ['table' => 'expenses', 'column' => 'recorded_by'],
            ['table' => 'commission_rules', 'column' => 'therapist_id'],
            ['table' => 'broadcasts', 'column' => 'created_by'],
            ['table' => 'patients', 'column' => 'referred_by'],
        ];
    }
}
