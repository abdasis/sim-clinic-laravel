<?php

namespace App\Enums;

enum BroadcastKind: string
{
    /** Pesan pemasaran — hanya untuk pasien yang memberi izin (opt-in). */
    case Promo = 'promo';

    /** Pengingat operasional perawatan — tidak tunduk pada opt-in promosi. */
    case Reminder = 'reminder';

    public function label(): string
    {
        return __('broadcast.kind.'.$this->value);
    }
}
