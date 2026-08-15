<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\CommissionRuleType;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([TenantScope::class])]
class CommissionRule extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'therapist_id',
        'type',
        'amount',
        'percent',
        'min_revenue',
        'is_active',
    ];

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    /**
     * Berlaku untuk terapis ini? Aturan tanpa terapis berlaku untuk semua.
     */
    public function appliesTo(?int $therapistId): bool
    {
        return $this->therapist_id === null || $this->therapist_id === $therapistId;
    }

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
