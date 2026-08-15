<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Sasaran satu promo: sebuah layanan atau sebuah produk.
 */
#[ScopedBy([TenantScope::class])]
class PromoItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'promo_id', 'promotable_type', 'promotable_id'];

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function promotable(): MorphTo
    {
        return $this->morphTo();
    }
}
