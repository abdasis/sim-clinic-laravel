<?php

namespace App\Enums;

enum ClinicRole: string
{
    case Admin = 'admin';
    case Doctor = 'doctor';
    case Therapist = 'therapist';
    case Cashier = 'cashier';

    public function label(): string
    {
        return __('clinic.role.'.$this->value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
