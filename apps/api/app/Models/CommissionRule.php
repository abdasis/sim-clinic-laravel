<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\CommissionRuleType;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([TenantScope::class])]
class CommissionRule extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'amount',
        'percent',
        'min_revenue',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CommissionRuleType::class,
            'amount' => 'decimal:2',
            'percent' => 'decimal:2',
            'min_revenue' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
