<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu ambang follow-up untuk seluruh klinik, dihitung dari rekam medis
 * terakhir pasien. Satu baris per klinik.
 */
#[ScopedBy([TenantScope::class])]
class BroadcastReminderSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'days', 'message_template_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }
}
