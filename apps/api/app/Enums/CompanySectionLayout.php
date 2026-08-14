<?php

namespace App\Enums;

/**
 * Tata letak section konten bebas: banner selebar halaman atau
 * dua kolom gambar-teks.
 */
enum CompanySectionLayout: string
{
    case Banner = 'banner';
    case Split = 'split';

    public function label(): string
    {
        return __('company_profile.section_layouts.'.$this->value);
    }
}
