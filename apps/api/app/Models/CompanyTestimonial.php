<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\IsCompanyContent;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([TenantScope::class])]
class CompanyTestimonial extends Model
{
    use BelongsToTenant, HasFactory, IsCompanyContent;

    protected $fillable = [
        'tenant_id',
        'quote',
        'author_name',
        'since_year',
        'avatar_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quote' => 'array',
            'since_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
