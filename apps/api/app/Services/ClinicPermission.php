<?php

namespace App\Services;

use App\Enums\ClinicRole;

/**
 * Matriks permission peran klinik → modul/aksi (research.md R2).
 * Dipakai semua Policy lewat Gate clinic.access.
 *
 * action: 'r' (view/viewAny), 'w' (create/update/delete/manage).
 */
class ClinicPermission
{
    /**
     * @var array<string, array<string, string>> role => [module => 'r'|'rw']
     */
    private const MATRIX = [
        'admin' => [
            'staff' => 'rw', 'service' => 'rw', 'patient' => 'rw', 'booking' => 'rw',
            'medical_record' => 'rw', 'product' => 'rw', 'inventory' => 'rw',
            'transaction' => 'rw', 'invoice' => 'rw', 'report' => 'rw',
        ],
        'doctor' => [
            'patient' => 'rw', 'booking' => 'rw', 'medical_record' => 'rw', 'service' => 'r',
        ],
        'therapist' => [
            'patient' => 'r', 'booking' => 'rw', 'medical_record' => 'rw', 'service' => 'r',
        ],
        'cashier' => [
            'patient' => 'rw', 'transaction' => 'rw', 'invoice' => 'rw',
        ],
    ];

    public function canAccess(?ClinicRole $role, string $module, string $action = 'r'): bool
    {
        if ($role === null) {
            return false;
        }

        $access = self::MATRIX[$role->value][$module] ?? null;

        if ($access === null) {
            return false;
        }

        if ($action === 'w') {
            return $access === 'rw';
        }

        return true;
    }
}
