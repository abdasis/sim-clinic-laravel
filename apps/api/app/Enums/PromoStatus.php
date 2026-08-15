<?php

namespace App\Enums;

enum PromoStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return __('clinic.promo_status.'.$this->value);
    }
}
