<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu pesan dalam percakapan chatbot, masuk maupun keluar.
 */
#[ScopedBy([TenantScope::class])]
class ChatMessage extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'sender_phone',
        'direction',
        'content',
        'role',
        'is_closing',
        'tool_name',
        'tool_call_id',
    ];

    protected function casts(): array
    {
        return [
            'is_closing' => 'boolean',
        ];
    }
}
