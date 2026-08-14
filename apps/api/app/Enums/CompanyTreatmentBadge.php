<?php

namespace App\Enums;

/**
 * Penanda kecil di kartu treatment landing — unggulan atau sedang berjalan.
 */
enum CompanyTreatmentBadge: string
{
    case Featured = 'featured';
    case Current = 'current';

    public function label(): string
    {
        return __('company_profile.treatment_badges.'.$this->value);
    }
}
