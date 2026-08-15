<?php

namespace App\Enums;

enum BroadcastRecipientStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return __('broadcast.status.'.$this->value);
    }
}
