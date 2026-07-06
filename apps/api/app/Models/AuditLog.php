<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Audit log native (spec 001, FR-028). tenant_id disimpan eksplisit,
 * subject = morph ke entitas terkait, causer = user pelaku.
 */
class AuditLog extends Model
{
    protected $fillable = ['tenant_id', 'action', 'subject_type', 'subject_id', 'causer_id', 'properties'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
}
