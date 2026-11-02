<?php

namespace App\Enums;

/**
 * Jalur WhatsApp yang dipakai sebuah percakapan, dan sekaligus jalur balasnya:
 * pesan yang masuk lewat Cloud API tidak bisa dibalas lewat sesi WAHA.
 */
enum ConversationChannel: string
{
    case Waha = 'waha';
    case CloudApi = 'cloud_api';

    public function label(): string
    {
        return __('inbox.channel.'.$this->value);
    }
}
