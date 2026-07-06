<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected $fillable = ['tenant_id', 'email', 'role', 'token', 'expires_at', 'status'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'status' => InvitationStatus::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast() || $this->status === InvitationStatus::Expired;
    }

    public static function defaultExpiry(): Carbon
    {
        return now()->addDays(7);
    }
}
