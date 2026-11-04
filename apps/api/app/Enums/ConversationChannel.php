<?php

namespace App\Enums;

/**
 * Jalur WhatsApp yang dipakai sebuah percakapan. Gateway internal adalah
 * satu-satunya jalur; nilai ini menyimpan asal pesan masuk.
 */
enum ConversationChannel: string
{
    case Gateway = 'gateway';

    public function label(): string
    {
        return __('inbox.channel.'.$this->value);
    }
}
