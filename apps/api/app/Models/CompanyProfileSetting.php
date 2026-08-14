<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Setelan tunggal company profile per tenant: identitas, kontak, meta SEO,
 * dan saklar publikasi landing.
 */
#[ScopedBy([TenantScope::class])]
class CompanyProfileSetting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'is_published',
        'brand_name',
        'tagline',
        'logo_path',
        'phone',
        'whatsapp',
        'email',
        'address',
        'map_embed_url',
        'social_links',
        'meta_title',
        'meta_description',
        'chat_widget_enabled',
        'chat_widget_number',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'chat_widget_enabled' => 'boolean',
            'brand_name' => 'array',
            'tagline' => 'array',
            'address' => 'array',
            'social_links' => 'array',
            'meta_title' => 'array',
            'meta_description' => 'array',
        ];
    }
}
