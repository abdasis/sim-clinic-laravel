<?php

namespace App\Enums;

enum CommissionRuleType: string
{
    /** Nominal tetap untuk tiap pasien yang dilayani. */
    case PerPatient = 'per_patient';

    /** Nominal tetap tambahan untuk pasien yang baru pertama kali datang. */
    case PerNewPatient = 'per_new_patient';

    /** Persentase dari omzet; berlaku bila omzet mencapai ambangnya. */
    case RevenuePercent = 'revenue_percent';

    public function label(): string
    {
        return __('commission.type.'.$this->value);
    }

    /** Aturan bertingkat: hanya ambang tertinggi yang terlampaui yang dipakai. */
    public function isTiered(): bool
    {
        return $this === self::RevenuePercent;
    }
}
