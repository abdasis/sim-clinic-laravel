<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    /** Keluar tanpa terjual: rusak, hilang, kedaluwarsa. */
    case OutManual = 'out_manual';

    /**
     * Habis dipakai klinik sendiri saat mengerjakan treatment.
     *
     * Dipisah dari OutManual karena artinya berbeda: yang terpakai
     * treatment adalah biaya layanan, sedangkan yang rusak adalah
     * kerugian. Menyatukannya membuat biaya bahan per periode tidak bisa
     * dihitung.
     */
    case UsedInternal = 'used_internal';
    case SoldPos = 'sold_pos';
    case Rollback = 'rollback';

    public function label(): string
    {
        return __('clinic.stock_movement_type.'.$this->value);
    }

    /**
     * Arah mutasi saldo: true = menambah, false = mengurangi.
     */
    public function isInbound(): bool
    {
        return in_array($this, [self::In, self::Rollback], true);
    }
}
