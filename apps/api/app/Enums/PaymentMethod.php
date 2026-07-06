<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Debit = 'debit';

    public function label(): string
    {
        return __('clinic.payment_method.'.$this->value);
    }
}
