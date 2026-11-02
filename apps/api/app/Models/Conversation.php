<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\ConversationChannel;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Satu utas percakapan WhatsApp dengan satu nomor kontak.
 */
#[ScopedBy([TenantScope::class])]
class Conversation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'contact_phone',
        'contact_name',
        'channel',
        'last_message_at',
        'unread_count',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ConversationChannel::class,
            'last_message_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Pesan terbaru untuk preview daftar percakapan. `id` jadi tie-break
     * ketika dua pesan punya `sent_at` sama.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->ofMany(['sent_at' => 'max', 'id' => 'max']);
    }
}
