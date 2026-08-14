<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\IsCompanyContent;
use App\Enums\CompanyLinkType;
use App\Enums\CompanyNavPosition;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([TenantScope::class])]
class CompanyNavigationItem extends Model
{
    use BelongsToTenant, HasFactory, IsCompanyContent;

    protected $fillable = [
        'tenant_id',
        'label',
        'url',
        'link_type',
        'position',
        'sort_order',
        'is_active',
        'is_cta',
    ];

    protected function casts(): array
    {
        return [
            'position' => CompanyNavPosition::class,
            'link_type' => CompanyLinkType::class,
            'label' => 'array',
            'is_active' => 'boolean',
            'is_cta' => 'boolean',
        ];
    }
}
