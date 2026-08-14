<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\IsCompanyContent;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([TenantScope::class])]
class CompanyBrand extends Model
{
    use BelongsToTenant, HasFactory, IsCompanyContent;

    protected $fillable = [
        'tenant_id',
        'name',
        'logo_path',
        'url',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
