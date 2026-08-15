<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\BroadcastAudience;
use App\Enums\BroadcastKind;
use App\Enums\BroadcastRecipientStatus;
use App\Enums\BroadcastStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([TenantScope::class])]
class Broadcast extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'title',
        'kind',
        'status',
        'message',
        'audience',
        'audience_params',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience' => BroadcastAudience::class,
            'kind' => BroadcastKind::class,
            'status' => BroadcastStatus::class,
            'audience_params' => 'array',
        ];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    public function sentRecipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class)
            ->where('status', BroadcastRecipientStatus::Sent);
    }

    public function pendingRecipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class)
            ->where('status', BroadcastRecipientStatus::Pending);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
