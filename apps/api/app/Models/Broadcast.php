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
use Illuminate\Support\Facades\Storage;

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
        'image_path',
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

    /**
     * Alamat publik gambar broadcast, atau null bila tidak ada. Dipakai
     * layar campaign untuk menampilkan posternya kembali.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path === null
            ? null
            : Storage::disk('public')->url($this->image_path);
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

    /**
     * Penerima yang pengirimannya ditolak gateway. Ikut dihitung supaya
     * layar campaign bisa menyebut angka gagalnya — tanpa itu blast yang
     * separuh gagal terbaca sama dengan yang mulus.
     */
    public function failedRecipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class)
            ->where('status', BroadcastRecipientStatus::Failed);
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
