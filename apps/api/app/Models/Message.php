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
        'reply_to_message_id',
        'reaction',
        'media_type',
        'media_path',
        'media_mime',
        'media_name',
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

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }
}
