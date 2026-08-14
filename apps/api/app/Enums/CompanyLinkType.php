<?php

namespace App\Enums;

/**
 * Jenis tautan menu navigasi: lompat ke section di halaman yang sama,
 * pindah route internal, atau keluar ke alamat lain.
 */
enum CompanyLinkType: string
{
    case AnchorSection = 'anchor_section';
    case RouteInternal = 'route_internal';
    case External = 'external';

    public function label(): string
    {
        return __('company_profile.link_types.'.$this->value);
    }
}
