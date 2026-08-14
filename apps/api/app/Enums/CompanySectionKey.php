<?php

namespace App\Enums;

/**
 * Slot section konten yang dikenali landing. Kuncinya tetap supaya
 * frontend tahu di mana section itu dirender, isinya bebas diatur admin.
 */
enum CompanySectionKey: string
{
    case PharmaBanner = 'pharma_banner';
    case BookingCta = 'booking_cta';
    case EstoreCta = 'estore_cta';

    public function label(): string
    {
        return __('company_profile.section_keys.'.$this->value);
    }
}
