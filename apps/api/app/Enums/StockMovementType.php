<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case OutManual = 'out_manual';
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
