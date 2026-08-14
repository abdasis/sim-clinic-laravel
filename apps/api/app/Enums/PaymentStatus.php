<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return __('clinic.payment_status.'.$this->value);
    }
}
