<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    public function label(): string
    {
        return __('inbox.status.'.$this->value);
    }
}
