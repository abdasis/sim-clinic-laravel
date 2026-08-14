<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Setelan tunggal company profile per tenant: identitas situs, kanal chat,
 * tautan sosial/marketplace, dan saklar publikasi landing.
 */
#[ScopedBy([TenantScope::class])]
class CompanyProfileSetting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'logo_path',
        'site_name',
        'copyright_text',
        'chat_channels',
        'social_links',
        'marketplace_links',
        'default_locale',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'site_name' => 'array',
            'chat_channels' => 'array',
            'social_links' => 'array',
            'marketplace_links' => 'array',
        ];
    }
}
