<?php

namespace App\Enums;

enum BroadcastAudience: string
{
    /** Semua pasien aktif yang punya nomor. */
    case All = 'all';

    /** Pasien yang kunjungan terakhirnya sudah lewat N hari — pengingat kembali. */
    case Inactive = 'inactive';

    /** Pasien yang pernah mengambil layanan tertentu. */
    case Service = 'service';

    public function label(): string
    {
        return __('broadcast.audience.'.$this->value);
    }
}
