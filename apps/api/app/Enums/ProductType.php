<?php

namespace App\Enums;

/**
 * Peruntukan sebuah barang. Keduanya sama-sama punya stok dan mutasi, yang
 * membedakan hanya apakah barangnya dijual ke pasien atau habis dipakai
 * terapis saat mengerjakan treatment.
 */
enum ProductType: string
{
    /** Dijual ke pasien lewat kasir. */
    case Retail = 'retail';

    /** Bahan pakai treatment — masker, spon, sarung tangan. Tidak dijual. */
    case Consumable = 'consumable';

    public function label(): string
    {
        return __('product.type_'.$this->value);
    }

    public function isSellable(): bool
    {
        return $this === self::Retail;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
