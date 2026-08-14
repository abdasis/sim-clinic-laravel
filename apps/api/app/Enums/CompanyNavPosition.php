<?php

namespace App\Enums;

enum CompanyNavPosition: string
{
    case Header = 'header';
    case Footer = 'footer';

    public function label(): string
    {
        return __('company_profile.nav_positions.'.$this->value);
    }
}
