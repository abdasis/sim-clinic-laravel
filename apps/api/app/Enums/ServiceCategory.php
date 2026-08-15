<?php

namespace App\Enums;

/**
 * Kategori treatment klinik. Labelnya lewat berkas lang supaya seluruh teks
 * yang tampil di layar tetap satu sumber dan berbahasa Indonesia.
 */
enum ServiceCategory: string
{
    case Skinbooster = 'skinbooster';
    case DermaTreatment = 'derma_treatment';
    case FacialPeeling = 'facial_peeling';
    case Other = 'other';

    public function label(): string
    {
        return __('service.category_'.$this->value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
