<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\MedicalPhotoType;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[ScopedBy([TenantScope::class])]
class MedicalPhoto extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'medical_record_id',
        'type',
        'path',
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'type' => MedicalPhotoType::class,
        ];
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
