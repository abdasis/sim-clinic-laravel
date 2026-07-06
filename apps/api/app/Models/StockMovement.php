<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\StockMovementType;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[ScopedBy([TenantScope::class])]
class StockMovement extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'type',
        'quantity',
        'balance_after',
        'related_type',
        'related_id',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
