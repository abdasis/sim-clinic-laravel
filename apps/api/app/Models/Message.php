<?php

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pesan masuk atau keluar. Tidak memakai tenant scope sendiri: pemiliknya
 * dibaca dari percakapan, dan setiap kueri selalu berangkat dari sana.
 */
class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'direction',
        'body',
        'provider_message_id',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
