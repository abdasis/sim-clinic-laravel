<?php

namespace App\Enums;

enum BroadcastStatus: string
{
    case Ready = 'ready';
    case Sending = 'sending';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Done = 'done';
    /**
     * Antrean habis tanpa satu pun pesan sampai. Dibedakan dari `Done`
     * supaya campaign yang seluruhnya gagal tidak terbaca "Selesai" —
     * admin menyangka pesannya terkirim padahal tidak satu pun berangkat.
     */
    case Failed = 'failed';

    public function label(): string
    {
        return __('broadcast.campaign_status.'.$this->value);
    }
}
