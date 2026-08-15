<?php

namespace App\Enums;

enum BroadcastStatus: string
{
    case Ready = 'ready';
    case Sending = 'sending';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Done = 'done';

    public function label(): string
    {
        return __('broadcast.campaign_status.'.$this->value);
    }
}
