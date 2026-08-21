<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jarak waktu pengingat sebelum jadwal, satu setelan untuk seluruh klinik.
 */
#[ScopedBy([TenantScope::class])]
class BookingReminderSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'is_active', 'offset_minutes', 'message_template_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'offset_minutes' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }
}
