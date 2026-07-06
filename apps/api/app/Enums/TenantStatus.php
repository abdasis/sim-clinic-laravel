<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return __('tenant.status.'.$this->value);
    }
}
