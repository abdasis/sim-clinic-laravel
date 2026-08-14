<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\IsCompanyContent;
use App\Enums\CompanyCtaType;
use App\Enums\CompanySectionKey;
use App\Enums\CompanySectionLayout;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Section konten bebas yang mengisi slot tetap di landing (banner farmasi,
 * ajakan booking, ajakan e-store).
 */
#[ScopedBy([TenantScope::class])]
class CompanyContentSection extends Model
{
    use BelongsToTenant, HasFactory, IsCompanyContent;

    protected $fillable = [
        'tenant_id',
        'section_key',
        'layout',
        'title',
        'body',
        'image_path',
        'cta_label',
        'cta_type',
        'cta_value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'section_key' => CompanySectionKey::class,
            'layout' => CompanySectionLayout::class,
            'cta_type' => CompanyCtaType::class,
            'title' => 'array',
            'body' => 'array',
            'cta_label' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
