<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\BookingReminderStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rencana pengingat untuk satu booking.
 */
#[ScopedBy([TenantScope::class])]
class BookingReminder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'remind_at',
        'reminder_offset_minutes',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
            'status' => BookingReminderStatus::class,
            'reminder_offset_minutes' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
