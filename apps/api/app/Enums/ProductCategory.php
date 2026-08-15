<?php

namespace App\Enums;

/**
 * Kategori produk retail klinik. Namanya istilah lini produk perawatan kulit
 * yang lazim ditulis apa adanya di kemasan, jadi labelnya tidak diterjemahkan.
 */
enum ProductCategory: string
{
    case FacialWash = 'facial_wash';
    case Toner = 'toner';
    case Sunscreen = 'sunscreen';
    case Serum = 'serum';
    case NightCream = 'night_cream';

    public function label(): string
    {
        return match ($this) {
            self::FacialWash => 'Facial Wash',
            self::Toner => 'Toner',
            self::Sunscreen => 'Sunscreen',
            self::Serum => 'Serum',
            self::NightCream => 'Night Cream',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
