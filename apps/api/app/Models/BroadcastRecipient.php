<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\BroadcastRecipientStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([TenantScope::class])]
class BroadcastRecipient extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'broadcast_id',
        'patient_id',
        'name',
        'phone',
        'message',
        'status',
        'sent_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'status' => BroadcastRecipientStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
