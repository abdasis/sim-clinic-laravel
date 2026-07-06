<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\PaymentMethod;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([TenantScope::class])]
class Payment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'transaction_id',
        'method',
        'amount',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
