<?php

namespace App\Enums;

enum UserRole: string
{
    case PlatformAdmin = 'platform_admin';
    case TenantAdmin = 'tenant_admin';
    case Member = 'member';

    public function label(): string
    {
        return __('tenant.role.'.$this->value);
    }
}
