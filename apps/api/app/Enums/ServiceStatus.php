<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return __('clinic.service_status.'.$this->value);
    }
}
