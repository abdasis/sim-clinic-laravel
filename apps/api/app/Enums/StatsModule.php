<?php

namespace App\Enums;

/**
 * Modul yang punya ringkasan statistik di halaman index admin.
 *
 * Nilainya sengaja memakai bentuk URL (kebab-case) supaya sama persis dengan
 * segmen route `/{tenant}/clinic/stats/{module}` dan folder route di frontend.
 */
enum StatsModule: string
{
    case Patients = 'patients';
    case Services = 'services';
    case Products = 'products';
    case Inventory = 'inventory';
    case MedicalRecords = 'medical-records';
    case Transactions = 'transactions';
    case Bookings = 'bookings';
    case Staff = 'staff';

    /**
     * Permission spatie yang harus dimiliki untuk membaca statistik modul ini.
     * Statistik hanya membaca, jadi cukup `.view` — bukan `.manage`.
     */
    public function permission(): string
    {
        return match ($this) {
            self::Patients => 'patient.view',
            self::Services => 'service.view',
            self::Products => 'product.view',
            self::Inventory => 'inventory.view',
            self::MedicalRecords => 'medical_record.view',
            self::Transactions => 'transaction.view',
            self::Bookings => 'booking.view',
            self::Staff => 'staff.view',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
