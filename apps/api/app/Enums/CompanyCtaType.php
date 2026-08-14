<?php

namespace App\Enums;

/**
 * Tujuan tombol ajakan di landing publik. Nilainya menentukan cara
 * `cta_value` dibaca: nama route, URL penuh, atau nomor WhatsApp.
 */
enum CompanyCtaType: string
{
    case RouteInternal = 'route_internal';
    case External = 'external';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return __('company_profile.cta_types.'.$this->value);
    }
}
