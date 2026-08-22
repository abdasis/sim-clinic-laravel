<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Setelan pesan otomatis seputar booking, satu baris untuk seluruh klinik:
 * jarak waktu pengingat sebelum jadwal, dan teks mana yang dipakai untuk
 * pengingat maupun konfirmasi.
 */
#[ScopedBy([TenantScope::class])]
class BookingReminderSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'is_active',
        'offset_minutes',
        'message_template_id',
        'confirmation_template_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'offset_minutes' => 'integer',
        ];
    }

    /** Teks pengingat sebelum jadwal. */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    /** Teks yang dikirim begitu klinik menekan konfirmasi di dasbor. */
    public function confirmationTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'confirmation_template_id');
    }
}
