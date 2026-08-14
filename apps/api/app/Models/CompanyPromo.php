<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\IsCompanyContent;
use App\Enums\CompanyCtaType;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([TenantScope::class])]
class CompanyPromo extends Model
{
    use BelongsToTenant, HasFactory, IsCompanyContent;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'image_path',
        'cta_label',
        'cta_url',
        'cta_type',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cta_type' => CompanyCtaType::class,
            'title' => 'array',
            'description' => 'array',
            'cta_label' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
